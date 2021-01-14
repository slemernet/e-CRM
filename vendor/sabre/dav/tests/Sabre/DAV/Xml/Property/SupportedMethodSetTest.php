<?php

declare(strict_types=1);

namespace Sabre\DAV\Xml\Property;

use Sabre\DAV\Xml\XmlTest;

class SupportedMethodSetTest extends XmlTest
{
    public function testSimple()
    {
        $cus = new SupportedMethodSet(['GET', 'PUT']);
        $this->assertEquals(['GET', 'PUT'], $cus->getValue());

        $this->assertTrue($cus->has('GET'));
        $this->assertFalse($cus->has('HEAD'));
    }

    public function testSerialize()
    {
        $cus = new SupportedMethodSet(['GET', 'PUT']);
        $xml = $this->write(['{DAV:}foo' => $cus]);

        $expected = '<?xml version="1.0"?>
<d:foo xmlns:d="DAV:">
    <d:supported-method name="GET"/>
    <d:supported-method name="PUT"/>
</d:foo>';

        $this->assertXmlStringEqualsXmlString($expected, $xml);
    }

    public function testSerializeHtml()
    {
        $cus = new SupportedMethodSet(['GET', 'PUT']);
        $result = $cus->toHtml(
            new \Sabre\DAV\Browser\HtmlOutputHelper('/', [])
        );

        $this->assertEquals('GET, PUT', $result);
    }
}
