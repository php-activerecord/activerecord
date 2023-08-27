<?php

namespace ActiveRecord\Serialize;

use ActiveRecord\Model;
use function ActiveRecord\denamespace;

/**
 * XML serializer.
 *
 * @package ActiveRecord
 */
class XmlSerializer extends Serialization
{
    private $writer;

    public function __construct(Model $model, &$options)
    {
        $this->includes_with_class_name_element = true;
        parent::__construct($model, $options);
    }

    public function to_s()
    {
        return $this->xml_encode();
    }

    private function xml_encode()
    {
        $this->writer = new \XMLWriter();
        $this->writer->openMemory();
        $this->writer->startDocument('1.0', 'UTF-8');
        $this->writer->startElement(strtolower(denamespace(($this->model))));
        $this->write($this->to_a());
        $this->writer->endElement();
        $this->writer->endDocument();
        $xml = $this->writer->outputMemory(true);

        if (true == @$this->options['skip_instruct']) {
            $xml = preg_replace('/<\?xml version.*?\?>/', '', $xml);
        }

        return $xml;
    }

    private function write($data, $tag = null)
    {
        foreach ($data as $attr => $value) {
            if (null != $tag) {
                $attr = $tag;
            }

            if (is_array($value) || is_object($value)) {
                if (!is_int(key($value))) {
                    $this->writer->startElement( denamespace($attr));
                    $this->write($value);
                    $this->writer->endElement();
                } else {
                    $this->write($value, $attr);
                }

                continue;
            }

            $this->writer->writeElement($attr, $value);
        }
    }
}
