<?php
// Template Element Definition
define('INDEX_SEPARATOR', '.');
define('ATTRIBUTE_SEPARATOR', ':');
define('BLOCK_TAG_START', '{%');
define('BLOCK_TAG_END', '%}');
define('VARIABLE_TAG_START', '{{');
define('VARIABLE_TAG_END', '}}');
define('COMMENT_TAG_START', '{#');
define('COMMENT_TAG_END', '#}');

define('VARIABLE_TAG_START_LEN', strlen(VARIABLE_TAG_START));
define('VARIABLE_TAG_LEN', strlen(VARIABLE_TAG_START)+strlen(VARIABLE_TAG_END));
define('BLOCK_TAG_START_LEN', strlen(BLOCK_TAG_START));
define('BLOCK_TAG_LEN', strlen(BLOCK_TAG_START)+strlen(BLOCK_TAG_END));
define('COMMENT_TAG_START_LEN', strlen(COMMENT_TAG_START));
define('COMMENT_TAG_LEN', strlen(COMMENT_TAG_START)+strlen(COMMENT_TAG_END));

// Node hierarchy
abstract class Node {
    private $special_variables = array(
        'loop.index' => '($loop_index+1)',
        'loop.index0' => '($loop_index)',
    );

    function __construct($content) {
        // trim $content by default
        // this is good for Block and Variable Tags
        $this->content = trim($content);
    }

    /**
     * Compile template expression to PHP expression.
     * `INDEX_SEPARATOR<key>` is mapped to `[key]` or `['key']` based on
     * whether key is consisted of digits
     * `ATTRIBUTE_SEPARATOR<attr>` is mapped to `->attr`
     * Predefined special variables like `loop.index` are handled as well.
     */
    function compile_expression($expression) {
        $attribute_pattern = sprintf('#(%s|%s)([^%s%s]+)#',
            preg_quote(INDEX_SEPARATOR),
            preg_quote(ATTRIBUTE_SEPARATOR),
            INDEX_SEPARATOR,
            ATTRIBUTE_SEPARATOR
        );

        if(isset($this->special_variables[$expression]))
            return $this->special_variables[$expression];

        $compiled_expression = preg_replace_callback($attribute_pattern, function($matches) {
            if($matches[1] === INDEX_SEPARATOR)
                return preg_match('#\d+#', $matches[2]) ? "[{$matches[2]}]" : "['{$matches[2]}']";
            else
                // must be ATTRIBUTE_SEPARATOR
                return "->{$matches[2]}";
        }, $expression);

        return "\${$compiled_expression}";
    }

    /**
     * Compile template statement to PHP statement.
     * Left for sub-classes to implement.
     */
    abstract function compile();
}

class VariableNode extends Node {
    function compile() {
        $compiled_expression = $this->compile_expression($this->content);

        return "<?php echo $compiled_expression; ?>";
    }
}

class BlockNode extends Node {
    private $simple_blocks = array(
        'endfor' => 'endforeach;',
        'endif' => 'endif;',
        'else' => 'else:',
    );

    function compile() {
        // simple blocks are handled first
        $simple_block = $this->simple_blocks[$this->content];
        if($simple_block)
            return "<?php $simple_block ?>";

        $parts = preg_split('#\s+#', $this->content);
        if(count($parts) === 4 && $parts[0] === 'for' && $parts[2] === 'in')
            // no $ leading first %s as compile_expression handles that
            return sprintf('<?php foreach(%s as $loop_index => $%s): ?>',
                $this->compile_expression($parts[3]),
                $parts[1]
            );
        else if(count($parts) === 2 && $parts[0] === 'if')
            return sprintf('<?php if(%s): ?>', $this->compile_expression($parts[1]));
        else
            throw new Exception;
    }
}

class CommentNode extends Node {
    function compile() {
        return "<?php // {$this->content} ?>";
    }
}

class TextNode extends Node {
    /**
     * No trimming to keep text verbatim.
     */
    function __construct($content) {
        $this->content = $content;
    }

    function compile() {
        return $this->content;
    }
}

/**
 * Translate $template_string to PHP-HTML string.
 * @param string $template_string string in sweep template syntax
 */
function sweep($template_string) {
    $tag_pattern = sprintf('@(%s.*?%s|%s.*?%s|%s.*?%s)@',
        preg_quote(BLOCK_TAG_START),
        preg_quote(BLOCK_TAG_END),
        preg_quote(VARIABLE_TAG_START),
        preg_quote(VARIABLE_TAG_END),
        preg_quote(COMMENT_TAG_START),
        preg_quote(COMMENT_TAG_END)
    );

    // split template string to template tags and non-tags
    $parts = preg_split($tag_pattern, $template_string, -1, PREG_SPLIT_DELIM_CAPTURE);

    $is_tag = false;
    $nodes = array_map(function($part) use (&$is_tag) {
        if($is_tag) {
            if(str_starts_with($part, VARIABLE_TAG_START))
                $node = new VariableNode(substr($part, VARIABLE_TAG_START_LEN, strlen($part)-VARIABLE_TAG_LEN));
            else if(str_starts_with($part, BLOCK_TAG_START))
                $node = new BlockNode(substr($part, BLOCK_TAG_START_LEN, strlen($part)-BLOCK_TAG_LEN));
            else if(str_starts_with($part, COMMENT_TAG_START))
                $node = new CommentNode(substr($part, COMMENT_TAG_START_LEN, strlen($part)-COMMENT_TAG_LEN));
        } else {
            $node = new TextNode($part);
        }

        // toggle flag since tags and non-tags are interlaced one after another
        $is_tag = !$is_tag;

        return $node;
    }, $parts);

    $compiled_content = implode('', array_map(function($node) { return $node->compile(); }, $nodes));
    return $compiled_content;
}

// thanks: http://stackoverflow.com/q/834303/1240620
function str_starts_with($haystack, $needle)
{
    return !strncmp($haystack, $needle, strlen($needle));
}

// thanks: http://www.codediesel.com/php/quick-way-to-determine-if-php-is-running-at-the-command-line/
function isCli() {
     return php_sapi_name() == 'cli' && empty($_SERVER['REMOTE_ADDR']);
}

if(isCli()) {
    if(count($argv) < 2) {
        printf("Usage: %s <template_file> [output_file]" . PHP_EOL, $argv[0]);
        exit(1);
    }

    $template = file_get_contents($argv[1]);
    $compiled_template = sweep($template);

    if(isset($argv[2]))
        file_put_contents($argv[2], $compiled_template);
    else
        echo $compiled_template . PHP_EOL;
}
