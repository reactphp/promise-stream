<?php

use React\Stream\ReadableStream;
use React\Promise;
use Clue\React\Promise\Stream;
use React\EventLoop\Factory;
use React\Promise\Timer;
use Clue\React\Block;

class UnwrapReadableTest extends TestCase
{
    private $loop;

    public function setUp()
    {
        $this->loop = Factory::create();
    }

    public function testReturnsReadableStreamForPromise()
    {
        $promise = new \React\Promise\Promise(function () { });
        $stream = Stream\unwrapReadable($promise);

        $this->assertTrue($stream->isReadable());
    }

    public function testClosingStreamMakesItNotReadable()
    {
        $promise = new \React\Promise\Promise(function () { });
        $stream = Stream\unwrapReadable($promise);

        $stream->close();

        $this->assertFalse($stream->isReadable());
    }

    public function testClosingStreamWillCancelInputPromiseAndMakeStreamNotReadable()
    {
        $promise = new \React\Promise\Promise(function () { }, $this->expectCallableOnce());
        $stream = Stream\unwrapReadable($promise);

        $stream->close();

        $this->assertFalse($stream->isReadable());
    }

    public function testEmitsErrorWhenPromiseRejects()
    {
        $promise = Timer\reject(0.001, $this->loop);

        $stream = Stream\unwrapReadable($promise);

        $this->assertTrue($stream->isReadable());

        $stream->on('error', $this->expectCallableOnce());

        $this->loop->run();

        $this->assertFalse($stream->isReadable());
    }

    public function testEmitsErrorWhenPromiseResolvesWithWrongValue()
    {
        $promise = Timer\resolve(0.001, $this->loop);

        $stream = Stream\unwrapReadable($promise);

        $this->assertTrue($stream->isReadable());

        $stream->on('error', $this->expectCallableOnce());

        $this->loop->run();

        $this->assertFalse($stream->isReadable());
    }

    public function testReturnsClosedStreamIfInputStreamIsClosed()
    {
        $input = new ReadableStream();
        $input->close();

        $promise = Promise\resolve($input);

        $stream = Stream\unwrapReadable($promise);

        $this->assertFalse($stream->isReadable());
    }

    public function testReturnsClosedStreamIfInputHasWrongValue()
    {
        $promise = Promise\resolve(42);

        $stream = Stream\unwrapReadable($promise);

        $this->assertFalse($stream->isReadable());
    }

    public function testReturnsStreamThatWillBeClosedWhenPromiseResolvesWithClosedInputStream()
    {
        $input = new ReadableStream();
        $input->close();

        $promise = Timer\resolve(0.001, $this->loop)->then(function () use ($input) {
            return $input;
        });

        $stream = Stream\unwrapReadable($promise);

        $this->assertTrue($stream->isReadable());

        $stream->on('close', $this->expectCallableOnce());

        $this->loop->run();

        $this->assertFalse($stream->isReadable());
    }

    public function testEmitsDataWhenInputEmitsData()
    {
        $input = new ReadableStream();

        $promise = Promise\resolve($input);
        $stream = Stream\unwrapReadable($promise);

        $stream->on('data', $this->expectCallableOnceWith('hello world'));
        $input->emit('data', array('hello world'));
    }

    public function testEmitsErrorAndClosesWhenInputEmitsError()
    {
        $input = new ReadableStream();

        $promise = Promise\resolve($input);
        $stream = Stream\unwrapReadable($promise);

        $stream->on('error', $this->expectCallableOnceWith(new \RuntimeException()));
        $stream->on('close', $this->expectCallableOnce());
        $input->emit('error', array(new \RuntimeException()));

        $this->assertFalse($stream->isReadable());
    }

    public function testEmitsEndAndClosesWhenInputEmitsEnd()
    {
        $input = new ReadableStream();

        $promise = Promise\resolve($input);
        $stream = Stream\unwrapReadable($promise);

        $stream->on('end', $this->expectCallableOnce());
        $stream->on('close', $this->expectCallableOnce());
        $input->emit('end', array());

        $this->assertFalse($stream->isReadable());
    }

    public function testEmitsCloseOnlyOnceWhenClosingStreamMultipleTimes()
    {
        $promise = new Promise\Promise(function () { });
        $stream = Stream\unwrapReadable($promise);

        $stream->on('close', $this->expectCallableOnce());

        $stream->close();
        $stream->close();
    }
}
