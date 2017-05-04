<?php

namespace Negotiation\Tests;

use Negotiation\Exception\InvalidArgument;
use Negotiation\Exception\InvalidMediaType;
use Negotiation\Negotiator;
use Negotiation\Accept;
use Negotiation\Match;

class NegotiatorTest extends TestCase
{

    /**
     * @var Negotiator
     */
    private $negotiator;

    protected function setUp()
    {
        $this->negotiator = new Negotiator();
    }

    /**
     * @dataProvider dataProviderForTestGetBest
     */
    public function testGetBest($header, $priorities, $expected)
    {
        try {
            $acceptHeader = $this->negotiator->getBest($header, $priorities);
        } catch (\Exception $e) {
            $this->assertEquals($expected, $e);

            return;
        }

        if ($acceptHeader === null) {
            $this->assertNull($expected);

            return;
        }

        $this->assertInstanceOf('Negotiation\Accept', $acceptHeader);

        $this->assertSame($expected[0], $acceptHeader->getType());
        $this->assertSame($expected[1], $acceptHeader->getParameters());
    }

    public static function dataProviderForTestGetBest()
    {
        $pearAcceptHeader = 'text/html,application/xhtml+xml,application/xml;q=0.9,text/*;q=0.7,*/*,image/gif; q=0.8, image/jpeg; q=0.6, image/*';
        $rfcHeader = 'text/*;q=0.3, text/html;q=0.7, text/html;level=1, text/html;level=2;q=0.4, */*;q=0.5';

        return array(
            # exceptions
            array('/qwer', array('f/g'), null),
            array('/qwer,f/g', array('f/g'), array('f/g', array())),
            array('foo/bar', array('/qwer'), new InvalidMediaType()),
            array('', array('foo/bar'), new InvalidArgument('The header string should not be empty.')),
            array('*/*', array(), new InvalidArgument('A set of server priorities should be given.')),

            # See: http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html
            array($rfcHeader, array('text/html;level=1'), array('text/html', array('level' => '1'))),
            array($rfcHeader, array('text/html'), array('text/html', array())),
            array($rfcHeader, array('text/plain'), array('text/plain', array())),
            array($rfcHeader, array('image/jpeg',), array('image/jpeg', array())),
            array($rfcHeader, array('text/html;level=2'), array('text/html', array('level' => '2'))),
            array($rfcHeader, array('text/html;level=3'), array('text/html', array( 'level' => '3'))),

            array('text/*;q=0.7, text/html;q=0.3, */*;q=0.5, image/png;q=0.4', array('text/html', 'image/png'), array('image/png', array())),
            array('image/png;q=0.1, text/plain, audio/ogg;q=0.9', array('image/png', 'text/plain', 'audio/ogg'), array('text/plain', array())),
            array('image/png, text/plain, audio/ogg', array('baz/asdf'), null),
            array('image/png, text/plain, audio/ogg', array('audio/ogg'), array('audio/ogg', array())),
            array('image/png, text/plain, audio/ogg', array('YO/SuP'), null),
            array('text/html; charset=UTF-8, application/pdf', array('text/html; charset=UTF-8'), array('text/html', array('charset' => 'UTF-8'))),
            array('text/html; charset=UTF-8, application/pdf', array('text/html'), null),
            array('text/html, application/pdf', array('text/html; charset=UTF-8'), array('text/html', array('charset' => 'UTF-8'))),
            # PEAR HTTP2 tests - have been altered from original!
            array($pearAcceptHeader, array('image/gif', 'image/png', 'application/xhtml+xml', 'application/xml', 'text/html', 'image/jpeg', 'text/plain',), array('image/png', array())),
            array($pearAcceptHeader, array('image/gif', 'application/xhtml+xml', 'application/xml', 'image/jpeg', 'text/plain',), array('application/xhtml+xml', array())),
            array($pearAcceptHeader, array('image/gif', 'application/xml', 'image/jpeg', 'text/plain',), array('application/xml', array())),
            array($pearAcceptHeader, array('image/gif', 'image/jpeg', 'text/plain'), array('image/gif', array())),
            array($pearAcceptHeader, array('text/plain', 'image/png', 'image/jpeg'), array('image/png', array())),
            array($pearAcceptHeader, array('image/jpeg', 'image/gif',), array('image/gif', array())),
            array($pearAcceptHeader, array('image/png',), array('image/png', array())),
            array($pearAcceptHeader, array('audio/midi',), array('audio/midi', array())),
            array('text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', array( 'application/rss+xml'), array('application/rss+xml', array())),
            # LWS / case sensitivity
            array('text/* ; q=0.3, TEXT/html ;Q=0.7, text/html ; level=1, texT/Html ;leVel = 2 ;q=0.4, */* ; q=0.5', array( 'text/html; level=2'), array('text/html', array( 'level' => '2'))),
            array('text/* ; q=0.3, text/html;Q=0.7, text/html ;level=1, text/html; level=2;q=0.4, */*;q=0.5', array( 'text/HTML; level=3'), array('text/html', array( 'level' => '3'))),
            # Incompatible
            array('text/html', array( 'application/rss'), null),
            # IE8 Accept header
            array('image/jpeg, application/x-ms-application, image/gif, application/xaml+xml, image/pjpeg, application/x-ms-xbap, */*', array( 'text/html', 'application/xhtml+xml'), array('text/html', array())),
            # Quality of source factors
            array($rfcHeader, array('text/html;q=0.4', 'text/plain'), array('text/plain', array())),
            # Wildcard "plus" parts (e.g., application/vnd.api+json)
            array('application/vnd.api+json', array('application/json', 'application/*+json'), array('application/*+json', array())),
            array($pearAcceptHeader, array('application/*+xml'), array('application/*+xml', array())),
        );
    }

    /**
     * @dataProvider dataProviderForTestGetOrderedElements
     */
    public function testGetOrderedElements($header, $expected)
    {
        try {
            $elements = $this->negotiator->getOrderedElements($header);
        } catch (\Exception $e) {
            $this->assertEquals($expected, $e);

            return;
        }

        if (empty($elements)) {
            $this->assertNull($expected);

            return;
        }

        $this->assertInstanceOf('Negotiation\Accept', $elements[0]);

        foreach ($expected as $key => $item) {
            $this->assertSame($item, $elements[$key]->getValue());
        }
    }

    public static function dataProviderForTestGetOrderedElements()
    {
        return array(
            // error cases
            array('', new InvalidArgument('The header string should not be empty.')),
            array('/qwer', null),

            // first one wins as no quality modifiers
            array('text/html, text/xml', array('text/html', 'text/xml')),

            // ordered by quality modifier
            array(
                'text/html;q=0.3, text/html;q=0.7',
                array('text/html;q=0.7', 'text/html;q=0.3')
            ),
            // ordered by quality modifier - the one with no modifier wins, level not taken into account
            array(
                'text/*;q=0.3, text/html;q=0.7, text/html;level=1, text/html;level=2;q=0.4, */*;q=0.5',
                array('text/html;level=1', 'text/html;q=0.7', '*/*;q=0.5', 'text/html;level=2;q=0.4', 'text/*;q=0.3')
            ),
        );
    }

    public function testGetBestRespectsQualityOfSource()
    {
        $accept = $this->negotiator->getBest('text/html,text/*;q=0.7', array('text/html;q=0.5', 'text/plain;q=0.9'));
        $this->assertInstanceOf('Negotiation\Accept', $accept);
        $this->assertEquals('text/plain', $accept->getType());
    }

   /**
    * @expectedException Negotiation\Exception\InvalidMediaType
    */
    public function testGetBestInvalidMediaType()
    {
        $header = 'sdlfkj20ff; wdf';
        $priorities = array('foo/qwer');

        $this->negotiator->getBest($header, $priorities, true);
    }

    /**
     * @dataProvider dataProviderForTestParseHeader
     */
    public function testParseHeader($header, $expected)
    {
        $accepts = $this->call_private_method('Negotiation\Negotiator', 'parseHeader', $this->negotiator, array($header));

        $this->assertSame($expected, $accepts);
    }

    public static function dataProviderForTestParseHeader()
    {
        return array(
            array('text/html ;   q=0.9', array('text/html ;   q=0.9')),
            array('text/html,application/xhtml+xml', array('text/html', 'application/xhtml+xml')),
            array(',,text/html;q=0.8 , , ', array('text/html;q=0.8')),
            array('text/html;charset=utf-8; q=0.8', array('text/html;charset=utf-8; q=0.8')),
            array('text/html; foo="bar"; q=0.8 ', array('text/html; foo="bar"; q=0.8')),
            array('text/html; foo="bar"; qwer="asdf", image/png', array('text/html; foo="bar"; qwer="asdf"', "image/png")),
            array('text/html ; quoted_comma="a,b  ,c,",application/xml;q=0.9,*/*;charset=utf-8; q=0.8', array('text/html ; quoted_comma="a,b  ,c,"', 'application/xml;q=0.9', '*/*;charset=utf-8; q=0.8')),
            array('text/html, application/json;q=0.8, text/csv;q=0.7', array('text/html', 'application/json;q=0.8', 'text/csv;q=0.7'))
        );
    }

    /**
     * @dataProvider dataProviderForTestFindMatches
     */
    public function testFindMatches($headerParts, $priorities, $expected)
    {
        $neg = new Negotiator();

        $matches = $this->call_private_method('Negotiation\Negotiator', 'findMatches', $neg, array($headerParts, $priorities));

        $this->assertEquals($expected, $matches);
    }

    public static function dataProviderForTestFindMatches()
    {
        return array(
            array(
                array(new Accept('text/html; charset=UTF-8'), new Accept('image/png; foo=bar; q=0.7'), new Accept('*/*; foo=bar; q=0.4')),
                array(new Accept('text/html; charset=UTF-8'), new Accept('image/png; foo=bar'), new Accept('application/pdf')),
                array(
                    new Match(1.0, 111, 0),
                    new Match(0.7, 111, 1),
                    new Match(0.4, 1,   1),
                )
            ),
            array(
                array(new Accept('text/html'), new Accept('image/*; q=0.7')),
                array(new Accept('text/html; asfd=qwer'), new Accept('image/png'), new Accept('application/pdf')),
                array(
                    new Match(1.0, 110, 0),
                    new Match(0.7, 100, 1),
                )
            ),
            array( # https://tools.ietf.org/html/rfc7231#section-5.3.2
                array(new Accept('text/*; q=0.3'), new Accept('text/html; q=0.7'), new Accept('text/html; level=1'), new Accept('text/html; level=2; q=0.4'), new Accept('*/*; q=0.5')),
                array(new Accept('text/html; level=1'), new Accept('text/html'), new Accept('text/plain'), new Accept('image/jpeg'), new Accept('text/html; level=2'), new Accept('text/html; level=3')),
                array(
                    new Match(0.3,    100,    0),
                    new Match(0.7,    110,    0),
                    new Match(1.0,    111,    0),
                    new Match(0.5,      0,    0),
                    new Match(0.3,    100,    1),
                    new Match(0.7,    110,    1),
                    new Match(0.5,      0,    1),
                    new Match(0.3,    100,    2),
                    new Match(0.5,      0,    2),
                    new Match(0.5,      0,    3),
                    new Match(0.3,    100,    4),
                    new Match(0.7,    110,    4),
                    new Match(0.4,    111,    4),
                    new Match(0.5,      0,    4),
                    new Match(0.3,    100,    5),
                    new Match(0.7,    110,    5),
                    new Match(0.5,      0,    5),
                )
            )
        );
    }
}
