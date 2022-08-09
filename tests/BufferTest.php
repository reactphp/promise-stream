<?php

namespace React\Tests\Promise\Stream;

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

        $stream->emit('error', array(new \RuntimeException('test', 42)));

        $promise->then(null, $this->expectCallableOnceWith(new \RuntimeException(
            'An error occured on the underlying stream while buffering: test',
            42,
            new \RuntimeException('test', 42)
        )));
    }

    public function testEmittingErrorAfterEmittingDataOnStreamRejects()
    {
        $stream = new ThroughStream();
        $promise = Stream\buffer($stream);

        $stream->emit('data', array('hello', $stream));
        $stream->emit('error', array(new \RuntimeException('test', 42)));

        $promise->then(null, $this->expectCallableOnceWith(new \RuntimeException(
            'An error occured on the underlying stream while buffering: test',
            42,
            new \RuntimeException('test', 42)
        )));
    }

    public function testCancelPendingStreamWillReject()
    {
        $stream = new ThroughStream();

        $promise = Stream\buffer($stream);

        $promise->cancel();

        $this->expectPromiseReject($promise);
    }

    public function testMaximumSize()
    {
        $stream = new ThroughStream();

        $promise = Stream\buffer($stream, 16);

        $stream->write('12345678910111213141516');

        $promise->then(null, $this->expectCallableOnceWith(new \OverflowException(
            'Buffer exceeded maximum length'
        )));
    }

    public function testUnderMaximumSize()
    {
        $stream = new ThroughStream();

        $promise = Stream\buffer($stream, 16);

        $stream->write('1234567891011');
        $stream->end();

        $promise->then($this->expectCallableOnceWith('1234567891011'));
    }
}
