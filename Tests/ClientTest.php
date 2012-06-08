<?php

namespace Slot\HttpBundle\Tests;

use Slot\HttpBundle\Client;
use Monolog\Logger;
use Symfony\Component\HttpKernel\Util\Filesystem;

/**
 * Created by JetBrains PhpStorm.
 * User: svenloth
 * Date: 08.06.12
 * Time: 12:23
 * To change this template use File | Settings | File Templates.
 */
class ClientTest extends \PHPUnit_Framework_TestCase
{
    public function testUrlSplit()
    {

        $c = $this->getClient();

        $c->setUrl('http://www.test.tld/controller/action?a=1&b=2');
        $c->prepareUrl();

        $this->assertEquals($c->getSsl(), false);

        $this->assertEquals($c->getHost(), 'www.test.tld');
        $this->assertEquals($c->getPort(), 80);
        $this->assertEquals($c->getPath(), '/controller/action');
        $this->assertEquals($c->getQuery(), 'a=1&b=2');


    }

    public function testSecureUrlSplit()
    {
        $c = $this->getClient();

        $c->setUrl('https://secure.test.tld');
        $c->prepareUrl();

        $this->assertEquals($c->getSsl(), true);
        $this->assertEquals($c->getHost(), 'secure.test.tld');
        $this->assertEquals($c->getPort(), 443);
        $this->assertEquals($c->getPath(), '/');
        $this->assertEquals($c->getQuery(), null);

    }

    public function testUrlSplitAllParams()
    {
        $c = $this->getClient();

        $c->setUrl('http://indiana:holygrail@www.test.tld:666/controller/action?a=1&b=2');
        $c->prepareUrl();

        $this->assertEquals($c->getSsl(), false);

        $this->assertEquals($c->getHost(), 'www.test.tld');
        $this->assertEquals($c->getPort(), 666);
        $this->assertEquals($c->getUser(), 'indiana');
        $this->assertEquals($c->getPassword(), 'holygrail');
        $this->assertEquals($c->getPath(), '/controller/action');
        $this->assertEquals($c->getQuery(), 'a=1&b=2');
    }



    protected function getClient()
    {

        return new Client(new Logger('testlogger'), new Filesystem());

    }


}