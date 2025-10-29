<?php

class MultiotpXmlParser
/**
 * @class     MultiotpXmlParser
 * @brief     New rewritten PHP XML parser (without eval)
 *
 * @author    Andre Liechti, SysCo systemes de communication sa, <info@multiotp.net>
 * @version   5.10.0.1
 * @date      2025-10-22
 * @since     2025-10-22
 *
 * Based on the following GNU Lesser General Public License work:
 * XML Parser Class
 * Parses an XML document into an object structure much like the SimpleXML extension.
 * @author Adam A. Flynn <adamaflynn@criticaldevelopment.net>
 * @copyright Copyright (c) 2005-2007, Adam A. Flynn
 * @version 1.3.0.1
 *
 *
 * Change Log
 *
 *   2025-10-22 5.10.0.1 SysCo/al New rewritten PHP XML parser (without eval)
 */
{
  public $xml;
  public $document;
  public $stack;
  public $cleanTagNames;

  public function __construct(
    $xml = '',
    $cleanTagNames = true
  ) {
    $this->xml = $xml;
    $this->stack = array();
    $this->cleanTagNames = $cleanTagNames;
  }

  public function Parse()
  {
    $parser = xml_parser_create();
    xml_set_element_handler($parser, array($this, 'StartElement'), array($this, 'EndElement'));
    xml_set_character_data_handler($parser, array($this, 'CharacterData'));

    if (!xml_parse($parser, $this->xml)) {
      $this->HandleError(
        xml_get_error_code($parser),
        xml_get_current_line_number($parser),
        xml_get_current_column_number($parser),
        xml_get_current_byte_index($parser)
      );
    }

    xml_parser_free($parser);
  }

  public function HandleError(
    $code,
    $line,
    $col,
    $byte_index = 0
  ) {
    $sample_size = 80;
    $sample_start = $byte_index - ($sample_size / 2);
    if ($sample_start < 0) {
      $sample_start = 0;
    }
    trigger_error(
      'XML Parsing Error at '.$line.':'.$col.
      (($byte_index != 0)?' (byte index: '.$byte_index.')':'').
      '. Error '.$code.': '.xml_error_string($code).
      ' Sample: '.htmlentities(substr($this->xml, $sample_start, $sample_size)),
      E_USER_ERROR
    );
  }

  public function GenerateXML()
  {
    return $this->document->GetXML();
  }

  public function StartElement(
    $parser,
    $name,
    $attrs = array()
  ) {
    $name = strtolower($name);

    if (count($this->stack) === 0) {
      // Root node
      $this->document = new MultiotpXMLTag($name, $attrs);
      $this->stack[] = $this->document;
      return;
    }

    $parent = end($this->stack);
    $child = $parent->AddChild($name, $attrs, count($this->stack), $this->cleanTagNames);
    $this->stack[] = $child;
  }

  public function EndElement(
    $parser,
    $name
  ) {
    array_pop($this->stack);
  }

  public function CharacterData(
    $parser,
    $data
  ) {
    $current = end($this->stack);
    if ($current !== false) {
      $current->tagData .= trim($data);
    }
  }
}

/**
 * XML Tag Object
 */
class MultiotpXMLTag
{
  public $tagName;
  public $tagAttrs;
  public $tagData;
  public $tagChildren;
  public $tagParents;

  public function __construct(
    $name,
    $attrs = array(),
    $parents = 0,
    $cleanTagName = true
  ) {
    $this->tagName = $cleanTagName ? str_replace(array(':', '-'), '_', strtolower($name)) : strtolower($name);
    $this->tagAttrs = array_change_key_case($attrs, CASE_LOWER);
    $this->tagData = '';
    $this->tagChildren = array();
    $this->tagParents = $parents;
  }

  /**
   * Helper for legagy compatibility
   * $parent->tagChildren['foo'][0] works also like the legacy $parent->foo[0] 
   */
  public function __get(
    $name
  ) {
    $name = strtolower($name);
    if (isset($this->tagChildren[$name])) {
      return $this->tagChildren[$name];
    }
    return array();
  }

  public function AddChild(
    $name,
    $attrs = array(),
    $parents = 0,
    $cleanTagName = true
  ) {
    $childName = $cleanTagName ? str_replace(array(':', '-'), '_', strtolower($name)) : strtolower($name);
    $child = new self($name, $attrs, $parents, $cleanTagName);

    if (!isset($this->tagChildren[$childName])) {
      $this->tagChildren[$childName] = array();
    }
    $this->tagChildren[$childName][] = $child;

    return $child;
  }

  public function GetChild(
    $name,
    $index = 0
  ) {
    $name = strtolower($name);
    return isset($this->tagChildren[$name][$index]) ? $this->tagChildren[$name][$index] : null;
  }

  public function GetXML()
  {
    $out = "\n" . str_repeat("\t", $this->tagParents) . '<'.$this->tagName;

    foreach ($this->tagAttrs as $attr => $value) {
      $out .= ' '.$attr.'="'.$value.'"';
    }

    if (empty($this->tagChildren) && $this->tagData === '') {
      $out .= " />";
    } else {
      $out .= ">";
      if ($this->tagData !== '') {
        $out .= $this->tagData;
      }
      foreach ($this->tagChildren as $children) {
        foreach ($children as $child) {
          $out .= $child->GetXML();
        }
      }
      $out .= '</'.$this->tagName.'>';
    }

    return $out;
  }
}
?>