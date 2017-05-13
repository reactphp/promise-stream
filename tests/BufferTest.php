<?php

use React\Promise\Stream;
use React\Stream\ThroughStream;

class BufferTest extends TestCase
{
    public function testClosedStreamResolvesWithEmptyBuffer()
    {
        $stream = new ThroughStream();
        $stream->close();

        $promise = Stream\buffer($stream);

        $this->expectPromiseResolveWith('', $promise);
    }

    public function testPendingStreamWillNotResolve()
    {
        $stream = new ThroughStream();

        $promise = Stream\buffer($stream);

        $promise->then($this->expectCallableNever(), $this->expectCallableNever());
    }

    public function testClosingStreamResolvesWithEmptyBuffer()
    {
        $stream = new ThroughStream();
        $promise = Stream\buffer($stream);

        $stream->close();

        $this->expectPromiseResolveWith('', $promise);
    }

    public function testEmittingDataOnStreamResolvesWithConcatenatedData()
    {
        $stream = new ThroughStream();
        $promise = Stream\buffer($stream);

        $stream->emit('data', array('hello', $stream));
        $stream->emit('data', array('world', $stream));
        $stream->close();

        $this->expectPromiseResolveWith('helloworld', $promise);
    }

    public function testEmittingErrorOnStreamRejects()
    {
        $stream = new ThroughStream();
        $promise = Stream\buffer($stream);

        $stream->emit('error', array(new \RuntimeException('test')));

        $this->expectPromiseReject($promise);
    }

    public function testEmittingErrorAfterEmittingDataOnStreamRejects()
    {
        $stream = new ThroughStream();
        $promise = Stream\buffer($stream);

        $stream->emit('data', array('hello', $stream));
        $stream->emit('error', array(new \RuntimeException('test')));

        $this->expectPromiseReject($promise);
    }

    public function testCancelPendingStreamWillReject()
    {
        $stream = new ThroughStream();

        $promise = Stream\buffer($stream);

        $promise->cancel();

        $this->expectPromiseReject($promise);
    }
}
