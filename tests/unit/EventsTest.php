<?php

namespace Test\Services\Events;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Services\Events\Events;

/**
 * Class EventsTest.
 *
 * @author Matthew Piskorz <matthew@matthewpiskorz.com>.
 * @copyright 2019 Matthew Piskorz <matthew@matthewpiskorz.com>.
 * @license https://opensource.org/licenses/MIT The MIT license.
 * @link https://github.com/s1mpletru1h/php-events
 * @since File available since Release 1.0.0
 *
 * @covers \Services\Events\Events
 */
class EventsTest extends TestCase
{
    /**
     * @var Events $events An instance of "Events" to test.
     */
    private $events;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->events = new Events('test', Events::VERBOSE);
        $this->logfile = './tmp/logs/test.log';
        $this->master_logfile = './tmp/logs/master.log';
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        $this->events->reset(true);
    }

    /**
     * @covers \Services\Events\Events::__construct
     */
    public function testConstruct()
    {
        // arrange
        // act
        // assert
        $this->assertTrue(file_exists($this->logfile));
        $this->assertTrue(file_exists($this->master_logfile));
    }

    /**
     * @covers \Services\Events\Events::log
     */
    public function testLog()
    {
        // arrange
        // act
        $this->events->log('TEST_EVENT');
        // assert
        $line = $this->getLastLine($this->logfile);
        $this->assertContains("TEST_EVENT", $line);
    }

    /**
     * @covers \Services\Events\Events::sub
     */
    public function testSub()
    {
        // arrange
        $handler = $this->getTestCallback();
        // act
        $this->events->sub('TEST_SUB', $handler);
        // assert
        $this->assertContains('TEST_SUB', array_keys($GLOBALS['SUBSCRIBERS']));
    }

    /**
     * @covers \Services\Events\Events::pub
     */
    public function testPub()
    {
        // success case:
        // arrange
        $handler = $this->getTestCallback();
        $this->events->sub('TEST_SUB', $handler);
        // act
        $result = $this->events->pub('TEST_SUB:VERBOSE');
        $result = $this->events->pub('TEST_SUB:INFO');
        $result = $this->events->pub('TEST_SUB:WARNING');
        $result = $this->events->pub('TEST_SUB:CRITICAL');
        $result = $this->events->pub('TEST_SUB');
        // assert
        $this->assertEquals(100, $result[0]);

        // exception case:
        // arrange
        $handler = function (string $event, array $data = []) {
            throw new \Exception("Exception test.", 1);
        };
        // act
        $this->events->sub('TEST_EXCEPTION', $handler);
        // act
        $result = $this->events->pub('TEST_EXCEPTION');
        // assert
        $line = $this->getLastLine($this->logfile);
        $this->assertContains("ERROR - test - EXCEPTION", $line);
    }

    /**
     * @covers \Services\Events\Events::sub
     */
    public function testUnsub()
    {
        // arrange
        $handler = $this->getTestCallback();
        $this->events->sub('TEST_SUB', $handler);
        // act
        $this->events->unsub('TEST_SUB');
        // assert
        $this->assertEmpty(array_keys($GLOBALS['SUBSCRIBERS']['TEST_SUB']));
    }

    /**
     * @covers \Services\Events\Events::sub
     */
    public function testReset()
    {
        // arrange
        $handler = $this->getTestCallback();
        $this->events->sub('TEST_SUB', $handler);
        // act
        $this->events->reset(true);
        // assert
        $this->assertEmpty(array_keys($GLOBALS['SUBSCRIBERS']));
    }
    private function getLastLine(string $filename)
    {
        $file = file($filename);
        return $file[count($file) - 1];
    }

    private function getLastNLines(string $filename, int $numLines)
    {
        $file = file($filename);
        return array_slice($file, -$numLines);
    }

    private function getTestCallback()
    {
        return function (string $event, array $data = []) {
            return 100;
        };
    }
}
