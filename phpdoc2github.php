<?php

$filename = ($argc > 1) ? $argv[1] : 'structure.xml';
$output_dir = ($argc > 2) ? $argv[2] : '.';

if(!is_file($filename) || !is_dir($output_dir)) die('Usage: php ' . $argv[0] . ' path_to/structure.xml output_dir');

$xml = file_get_contents($filename);

$doc = new DOMDocument();
$doc->loadXML($xml);
$xpath = new DOMXpath($doc);

$classes = array();
foreach($node = $xpath->query('//class') as $class){
  $classes[] = $xpath->query('./name', $class)->item(0)->nodeValue;
}

/*
$link = array();
foreach($node = $xpath->query('//link') as $link){
  // links not working yet
}
*/

$simple_types = array('string', 'int', 'float', 'bool', 'mixed', 'array');

function doType($types){
  global $simple_types, $classes;
  $arr = array();
  
  foreach(explode('|', $types) as $type){
    $type = preg_replace('/^\\\\/', '', trim($type));
    switch(true){
      case '' == $type:
      case in_array($type, $simple_types):
        $arr[] = $type;
        break;
      case in_array($type, $classes):
        $arr[] = "[$type]($type)";
        break;
      default:
        $arr[] = "[$type](http://php.net/manual/en/class." . strtolower($type) . ".php)";
    }
  }
  return implode('|', $arr);
}

// http://php.net/manual/en/class.domdocument.php

foreach($node = $xpath->query('//class') as $class){
  ob_start();
  $class_name = $xpath->query('./name', $class)->item(0)->nodeValue;
  //echo "$class_name\n";
  echo "===============\n\n";
  echo "### Properties\n";
  echo "----------\n\n";


  foreach($xpath->query('./property[@visibility="public"]', $class) as $property){
    $name = ($el = $xpath->query('./name', $property)->item(0)) ? $el->nodeValue : 'unknown' ;
    $default = ($el = $xpath->query('./default[text()]', $property)->item(0)) ? ' = ' . $el->nodeValue : '';
    $type = ($el = $xpath->query('.//type', $property)->item(0)) ? $el->nodeValue : 'mixed' ;
    $description = ($el = $xpath->query('.//description', $property)->item(0)) ? $el->nodeValue : '' ;
    echo "#### " . preg_replace('/^\$/', '', $name) . "\n\n";
    echo "<code>\npublic " . doType($type) . " $name$default\n</code>\n\n";
    echo "$description\n\n";
  }

  echo "\n\n### Methods\n";
  echo "----------\n\n";

  foreach($xpath->query('./method[@visibility="public"]', $class) as $method){
    $name = ($el = $xpath->query('./name', $method)->item(0)) ? $el->nodeValue : 'unknown' ;
    $type = ($el = $xpath->query('.//tag[@name="return"]', $method)->item(0)) ? $el->getAttribute('type') : '' ;
    $description = ($el = $xpath->query('.//description', $method)->item(0)) ? $el->nodeValue : '' ;
  
    $param_names = array();
    $params = array();
    foreach($xpath->query('.//tag[@name="param"]', $method) as $param){
      $param_names[] = trim($param->getAttribute('variable'));
      $params[] = $param;
    }

    echo "#### " . preg_replace('/^\$/', '', $name) . "\n\n";
    echo "<code>\n" . doType($type) . " $name(" . implode(', ', $param_names) . ")\n</code>\n\n";
    echo "$description\n\n";
    if(!empty($params)){
      echo <<<EOF
##### Arguments

<table>
  <tr>
    <th>Name</th><th>Type</th><th>Description</th>
  </tr>
EOF;
      foreach($params as $param){
        $desc = ($el = $param->getAttribute('description')) ? '' . trim(strip_tags($param->getAttribute('description'))) : '';
        //echo "* " . trim($param->getAttribute('variable')) . " **" . trim($param->getAttribute('type')) . "**" . $desc . "\n";
        echo "<tr><td>" . trim($param->getAttribute('variable')) . "</td><td>" . doType($param->getAttribute('type')) . "</td><td>" . $desc . "</td></tr>";
      }
      echo "</table>\n\n";
    }
  }

  file_put_contents($output_dir . '/' . $class_name . '.md', ob_get_clean());
}

ob_start();

foreach($classes as $class){
  echo "* [$class](wiki/$class)\n";
}

file_put_contents($output_dir . '/Home.md', ob_get_clean());

?>