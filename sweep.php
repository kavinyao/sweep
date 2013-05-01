<?php
include_once('sweep.extra.php');

// Template Element Definition
define('INDEX_SEPARATOR', '.');
define('ATTRIBUTE_SEPARATOR', ':');
define('FILTER_SEPARATOR', '|');

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

    private $builtin_filters = array(
        // string filters
        'upper' => 'strtoupper',
        'lower' => 'strtolower',
        'capfirst' => 'ucfirst',
        'title' => 'ucwords',
        'striptags' => 'strip_tags',
        'urlencode' => 'urlencode',
        'linkify' => 'sweep_linkify',
        'escape' => 'htmlspecialchars',
        // array filters
        'random' => 'sweep_array_random',
        // strint or array filters
        'length' => 'sweep_length',
    );

    function __construct($content) {
        // trim $content by default
        // this is good for Block and Variable Tags
        $this->content = trim($content);
    }

    /**
     * Compile template expression to PHP expression.
     * `INDEX_SEPARATOR<key>` is mapped to `[key]` or `['key']` based on
     * whether key consists of only digits
     * `ATTRIBUTE_SEPARATOR<attr>` is mapped to `->attr`
     * Predefined special variables like `loop.index` are handled as well.
     */
    function compile_expression($raw_expression) {
        // split variable from filters
        $parts = explode(FILTER_SEPARATOR, $raw_expression);
        $expression = $parts[0];
        $filters = array_slice($parts, 1);

        $expr_format = '%s';
        // wrapping filters
        foreach($filters as $filter) {
            $function = $this->builtin_filters[$filter];
            if(is_null($function))
                throw new Exception("Unsupported filter $filter");
            $expr_format = "{$function}({$expr_format})";
        }

        // process variable expression
        $attribute_pattern = sprintf('#(%s|%s)([^%s%s]+)#',
            preg_quote(INDEX_SEPARATOR),
            preg_quote(ATTRIBUTE_SEPARATOR),
            INDEX_SEPARATOR,
            ATTRIBUTE_SEPARATOR
        );

        if(isset($this->special_variables[$expression]))
            return sprintf($expr_format, $this->special_variables[$expression]);

        $compiled_expression = preg_replace_callback($attribute_pattern, function($matches) {
            if($matches[1] === INDEX_SEPARATOR)
                return preg_match('#\d+#', $matches[2]) ? "[{$matches[2]}]" : "['{$matches[2]}']";
            else
                // must be ATTRIBUTE_SEPARATOR
                return "->{$matches[2]}";
        }, $expression);

        return sprintf($expr_format, "\$$compiled_expression");
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
            throw new Exception("Unsupported block syntax.");
    }
}

class CommentNode extends Node {
    function compile() {
        return "<?php // {$this->content} ?>";
    }
}

function sweep_process_tag($matches) {
    $statement = $matches[0];
    if(str_starts_with($statement, VARIABLE_TAG_START))
        $node = new VariableNode(substr($statement, VARIABLE_TAG_START_LEN, strlen($statement)-VARIABLE_TAG_LEN));
    else if(str_starts_with($statement, BLOCK_TAG_START))
        $node = new BlockNode(substr($statement, BLOCK_TAG_START_LEN, strlen($statement)-BLOCK_TAG_LEN));
    else if(str_starts_with($statement, COMMENT_TAG_START))
        $node = new CommentNode(substr($statement, COMMENT_TAG_START_LEN, strlen($statement)-COMMENT_TAG_LEN));
    else
        throw new Exception("Illegal tag statement `$statement`");

    return $node->compile();
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
    $compiled_template = preg_replace_callback($tag_pattern, 'sweep_process_tag', $template_string);
    return $compiled_template;
}

// thanks: http://stackoverflow.com/q/834303/1240620
function str_starts_with($haystack, $needle)
{
    return !strncmp($haystack, $needle, strlen($needle));
}

// thanks: http://www.codediesel.com/php/quick-way-to-determine-if-php-is-running-at-the-command-line/
function is_cli() {
    return php_sapi_name() == 'cli' && empty($_SERVER['REMOTE_ADDR']);
}

// TODO: do this only if not included
if(is_cli()) {
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
