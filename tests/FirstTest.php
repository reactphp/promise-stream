<?php

namespace React\Tests\Promise\Stream;

use React\Promise\Stream;
use React\Stream\ThroughStream;

class FirstTest extends TestCase
{
    public function testClosedReadableStreamRejects()
    {
        $stream = new ThroughStream();
        $stream->close();

        $promise = Stream\first($stream);

        $this->expectPromiseReject($promise);
    }

    public function testClosedWritableStreamRejects()
    {
        $stream = new ThroughStream();
        $stream->close();

        $promise = Stream\first($stream);

        $this->expectPromiseReject($promise);
    }

    public function testPendingStreamWillNotResolve()
    {
        $stream = new ThroughStream();

        $promise = Stream\first($stream);

        $promise->then($this->expectCallableNever(), $this->expectCallableNever());
    }

    public function testClosingStreamRejects()
    {
        $stream = new ThroughStream();
        $promise = Stream\first($stream);

        $stream->close();

        $this->expectPromiseReject($promise);
    }

    public function testClosingWritableStreamRejects()
    {
        $stream = new ThroughStream();
        $promise = Stream\first($stream);

        $stream->close();

        $this->expectPromiseReject($promise);
    }

    public function testClosingStreamResolvesWhenWaitingForCloseEvent()
    {
        $stream = new ThroughStream();
        $promise = Stream\first($stream, 'close');

        $stream->close();

        $this->expectPromiseResolve($promise);
    }

    public function testEmittingDataOnStreamResolvesWithFirstEvent()
    {
        $stream = new ThroughStream();
        $promise = Stream\first($stream);

        $stream->emit('data', array('hello', $stream));
        $stream->emit('data', array('world', $stream));
        $stream->close();

        $this->expectPromiseResolveWith('hello', $promise);
    }

    public function testEmittingErrorOnStreamWillReject()
    {
        $stream = new ThroughStream();
        $promise = Stream\first($stream);

        $stream->emit('error', array(new \RuntimeException('test', 42)));

        $promise->then(null, $this->expectCallableOnceWith(new \RuntimeException(
            'An error occured on the underlying stream while waiting for event: test',
            42,
            new \RuntimeException('test', 42)
        )));
    }

    public function testEmittingErrorResolvesWhenWaitingForErrorEvent()
    {
        $stream = new ThroughStream();
        $promise = Stream\first($stream, 'error');

        $stream->emit('error', array(new \RuntimeException('test')));

        $this->expectPromiseResolve($promise);
    }

    public function testCancelPendingStreamWillReject()
    {
        $stream = new ThroughStream();

        $promise = Stream\first($stream);

        $promise->cancel();

        $this->expectPromiseReject($promise);
    }

    public function testNoGarbageCollectionCyclesAfterClosingStream()
    {
        \gc_collect_cycles();
        $stream = new ThroughStream();
        $promise = Stream\first($stream);

        $stream->close();

        $this->assertSame(0, \gc_collect_cycles());
    }

    public function testShouldResolveWithoutCreatingGarbageCyclesAfterDataThenClose()
    {
        \gc_collect_cycles();

        $stream = new ThroughStream();

        $promise = Stream\first($stream);

        $stream->emit('data', array('hello', $stream));
        $stream->close();

        $this->expectPromiseResolve($promise);
        $this->assertSame(0, \gc_collect_cycles());
    }

    public function testCancelPendingStreamWillRejectWithoutCreatingGarbageCycles()
    {
        \gc_collect_cycles();
        $stream = new ThroughStream();

        $promise = Stream\first($stream);

        $promise->cancel();

        $this->expectPromiseReject($promise);
        $this->assertSame(0, \gc_collect_cycles());
    }
}
