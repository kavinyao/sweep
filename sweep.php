<?php
// Template Element Definition
define('OBJECT_ATTRIBUTE_SEPARATOR', '.');
define('BLOCK_TAG_START', '{%');
define('BLOCK_TAG_END', '%}');
define('VARIABLE_TAG_START', '{{');
define('VARIABLE_TAG_END', '}}');

define('VARIABLE_TAG_START_LEN', strlen(VARIABLE_TAG_START));
define('VARIABLE_TAG_LEN', strlen(VARIABLE_TAG_START)+strlen(VARIABLE_TAG_END));
define('BLOCK_TAG_START_LEN', strlen(BLOCK_TAG_START));
define('BLOCK_TAG_LEN', strlen(BLOCK_TAG_START)+strlen(BLOCK_TAG_END));

// Node hierarchy
abstract class Node {
    private $special_variables = array(
        'loop.index' => '($loop_index+1)',
        'loop.index0' => '($loop_index)',
    );

    function __construct($content) {
        $this->content = trim($content);
    }

    function compile_expression($expression) {
        $attribute_pattern = sprintf('#%s#', preg_quote(OBJECT_ATTRIBUTE_SEPARATOR));

        if(isset($this->special_variables[$expression]))
            return $this->special_variables[$expression];

        $compiled_expression = preg_replace($attribute_pattern, '->', $expression);
        return "\${$compiled_expression}";
    }

    abstract function compile();
}

class VariableNode extends Node {
    function compile() {
        $compiled_expression = $this->compile_expression($this->content);

        return "<?php echo $compiled_expression; ?>";
    }
}

class BlockNode extends Node {
    function compile() {
        // Ending blocks first
        if(str_starts_with($this->content, 'endfor'))
            return '<?php endforeach; ?>';
        if(str_starts_with($this->content, 'endif'))
            return '<?php endif; ?>';
        if(str_starts_with($this->content, 'else'))
            return '<?php else: ?>';

        $parts = preg_split('#\s+#', $this->content);
        if(count($parts) === 4 && $parts[0] === 'for' && $parts[2] === 'in')
            return sprintf('<?php foreach(%s as $loop_index => $%s): ?>',
                $this->compile_expression($parts[3]),
                $parts[1]
            );
        else if(count($parts) === 2 && $parts[0] === 'if')
            return sprintf('<?php if(%s): ?>' . PHP_EOL, $this->compile_expression($parts[1]));
        else
            throw new Exception;
    }
}

class TextNode extends Node {
    function compile() {
        return $this->content;
    }
}


function sweep($template_string) {
    $tag_pattern = sprintf('#(%s.*?%s|%s.*?%s)#',
        preg_quote(BLOCK_TAG_START),
        preg_quote(BLOCK_TAG_END),
        preg_quote(VARIABLE_TAG_START),
        preg_quote(VARIABLE_TAG_END)
    );

    $parts = preg_split($tag_pattern, $template_string, -1, PREG_SPLIT_DELIM_CAPTURE);

    $in_tag = false;
    $nodes = array_map(function($part) use (&$in_tag) {
        if($in_tag) {
            if(str_starts_with($part, VARIABLE_TAG_START))
                $node = new VariableNode(substr($part, VARIABLE_TAG_START_LEN, strlen($part)-VARIABLE_TAG_LEN));
            else if(str_starts_with($part, BLOCK_TAG_START))
                $node = new BlockNode(substr($part, BLOCK_TAG_START_LEN, strlen($part)-BLOCK_TAG_LEN));
        } else {
            $node = new TextNode($part);
        }

        $in_tag = !$in_tag;
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
