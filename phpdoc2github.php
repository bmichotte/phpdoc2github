<?php

$filename = ($argc > 1) ? $argv[1] : 'structure.xml';
$output_dir = ($argc > 2) ? $argv[2] : '.';
$home_page = ($argc > 3) ? $argv[3] : 'Home.md';
$language = ($argc > 4) ? $argv[4] : 'en';

if (!is_file($filename) || !is_dir($output_dir))
{
    die('Usage: php ' . $argv[0] . ' path_to/structure.xml output_dir [Homepage.md] [path_in_wiki]');
}

$xml = file_get_contents($filename);

$doc = new DOMDocument();
$doc->loadXML($xml);
$xpath = new DOMXpath($doc);

$classes = array();
foreach ($node = $xpath->query('//class') as $class)
{
    $classes[] = $xpath->query('./name', $class)->item(0)->nodeValue;
}

/*
$link = array();
foreach($node = $xpath->query('//link') as $link){
  // links not working yet
}
*/

$simple_types = array('string', 'int', 'integer', 'float', 'bool',
    'boolean', 'mixed', 'array', 'callable', 'double');

function doType($types)
{
    global $simple_types, $classes, $language;
    $arr = array();

    foreach (explode('|', $types) as $type)
    {
        $type = preg_replace('/^\\\\/', '', trim($type));
        $trimmedType = str_replace("[]", "", $type);
        switch (true)
        {
            case '' == $type:
            case ($trimmedType == 'null'):
                $arr[] = "";
                break;
            case in_array($trimmedType, $simple_types):
                if ($type == 'bool')
                {
                    $type = 'boolean';
                }
                if ($type == 'int')
                {
                    $type = 'integer';
                }
                $arr[] = "[$type](http://php.net/manual/$language/language.types." . strtolower($trimmedType) . ".php)";
                break;
            case in_array($trimmedType, $classes):
                $arr[] = "[$type]($trimmedType)";
                break;
            case (strpos('Zend_', $trimmedType) == 0):
                $zClass = str_replace('_', '.', str_replace('Zend_', '', $trimmedType));
                $arr[] = "[$type](http://framework.zend.com/apidoc/1.12/files/$zClass.html#\\$trimmedType)";
                break;
            default:
                $arr[] = "[$type](http://php.net/manual/$language/class." . strtolower($trimmedType) . ".php)";
        }
    }

    return implode('|', $arr);
}

// http://php.net/manual/en/class.domdocument.php

foreach ($node = $xpath->query('//class') as $class)
{
    ob_start();
    $class_name = $xpath->query('./name', $class)->item(0)->nodeValue;
    //echo "$class_name\n";
    $properties = $xpath->query('./property[@visibility="public"]', $class);
    if ($properties->length > 0)
    {
        echo "===============\n\n";
        echo "### Properties\n";
        echo "----------\n\n";

        foreach ($properties as $property)
        {
            $name = ($el = $xpath->query('./name', $property)->item(0)) ? $el->nodeValue : 'unknown';
            $default = ($el = $xpath->query('./default[text()]', $property)->item(0)) ? ' = ' . $el->nodeValue : '';
            $type = ($el = $xpath->query('.//type', $property)->item(0)) ? $el->nodeValue : 'mixed';
            $description = ($el = $xpath->query('.//description', $property)->item(0)) ? $el->nodeValue : '';
            echo "#### " . preg_replace('/^\$/', '', $name) . "\n\n";
            echo "<code>\npublic " . doType($type) . " $name$default\n</code>\n\n";
            echo "$description\n\n";
        }
    }

    $methods = $xpath->query('./method[@visibility="public"]', $class);
    if ($methods->length > 0)
    {
        echo "\n\n### Methods\n";
        echo "----------\n\n";

        foreach ($methods as $method)
        {
            $name = ($el = $xpath->query('./name', $method)->item(0)) ? $el->nodeValue : 'unknown';
            $type = ($el = $xpath->query('.//tag[@name="return"]', $method)->item(0)) ? $el->getAttribute('type') : '';
            $description = ($el = $xpath->query('.//description', $method)->item(0)) ? $el->nodeValue : '';

            $param_names = array();
            $params = array();
            foreach ($xpath->query('.//tag[@name="param"]', $method) as $param)
            {
                $param_names[] = trim($param->getAttribute('variable'));
                $params[] = $param;
            }

            echo "#### " . preg_replace('/^\$/', '', $name) . "\n\n";
            echo "<code>\n" . doType($type) . " $name(" . implode(', ', $param_names) . ")\n</code>\n\n";
            echo "$description\n\n";
            if (!empty($params))
            {
                echo <<<EOF
##### Arguments

| Name | Type | Description |
|------|------|-------------|

EOF;
                foreach ($params as $param)
                {
                    $desc = ($el = $param->getAttribute('description')) ? '' . trim(strip_tags($param->getAttribute('description'))) : '';
                    //echo "* " . trim($param->getAttribute('variable')) . " **" . trim($param->getAttribute('type')) . "**" . $desc . "\n";
                    echo "| " . trim($param->getAttribute('variable')) . " | " . doType($param->getAttribute('type')) . " | " . $desc . " |\n";
                }
                echo "\n\n";
            }
        }
    }

    file_put_contents($output_dir . '/' . $class_name . '.md', ob_get_clean());
}

$packages = array();
foreach ($node = $xpath->query('//class') as $class)
{
    $package = $class->getAttribute('package');
    if (!isset($packages[$package]))
    {
        $packages[$package] = array();
    }
    $packages[$package][] = $xpath->query('./name', $class)->item(0)->nodeValue;
}

ob_start();
foreach ($packages as $package => $classes)
{
    echo "\n## $package\n";
    foreach ($classes as $class)
    {
        echo "* [$class]($class)\n";
    }
}
echo "\nGenerated by [bmichotte's](https://github.com/bmichotte/phpdoc2github) [phpdoc2github fork](https://github.com/monkeysuffrage/phpdoc2github)";
file_put_contents($output_dir . '/' . $home_page, ob_get_clean());

?>