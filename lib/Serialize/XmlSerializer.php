<?php

namespace ActiveRecord\Serialize;

use function ActiveRecord\denamespace;

use ActiveRecord\Model;

/**
 * XML serializer.
 *
 * @phpstan-import-type SerializeOptions from Serialization
 *
 * @package ActiveRecord
 */
class XmlSerializer extends Serialization
{
    private \XMLWriter $writer;

    /**
     * @param SerializeOptions $options
     */
    public function __construct(Model $model, array $options)
    {
        $this->includes_with_class_name_element = true;
        parent::__construct($model, $options);
    }

    public function to_s(): string
    {
        $res = $this->xml_encode();
        assert(is_string($res));

        return $res;
    }

    private function xml_encode(): string
    {
        $this->writer = new \XMLWriter();
        $this->writer->openMemory();
        $this->writer->startDocument('1.0', 'UTF-8');
        $this->writer->startElement(strtolower(denamespace(get_class($this->model))));
        $this->write($this->to_a());
        $this->writer->endElement();
        $this->writer->endDocument();
        $xml = $this->writer->outputMemory();

        if ($this->options['skip_instruct'] ?? false) {
            $xml = preg_replace('/<\?xml version.*?\?>/', '', $xml);
        }

        assert(is_string($xml));

        return $xml;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function write(array $data, string $tag = null): void
    {
        foreach ($data as $attr => $value) {
            $attr = $tag ?? $attr;
            if (is_array($value)) {
                if (!is_int(key($value))) {
                    $this->writer->startElement(denamespace($attr));
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
