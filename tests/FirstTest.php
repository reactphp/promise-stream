<?php

use React\Stream\ReadableStream;
use Clue\React\Promise\Stream;
use React\Promise\CancellablePromiseInterface;
use React\Stream\WritableStream;

class FirstTest extends TestCase
{
    public function testClosedReadableStreamRejects()
    {
        $stream = new ReadableStream();
        $stream->close();

        $promise = Stream\first($stream);

        $this->expectPromiseReject($promise);
    }

    public function testClosedWritableStreamRejects()
    {
        $stream = new WritableStream();
        $stream->close();

        $promise = Stream\first($stream);

        $this->expectPromiseReject($promise);
    }

    public function testPendingStreamWillNotResolve()
    {
        $stream = new ReadableStream();

        $promise = Stream\first($stream);

        $promise->then($this->expectCallableNever(), $this->expectCallableNever());
    }

    public function testClosingStreamRejects()
    {
        $stream = new ReadableStream();
        $promise = Stream\first($stream);

        $stream->close();

        $this->expectPromiseReject($promise);
    }

    public function testClosingWritableStreamRejects()
    {
        $stream = new WritableStream();
        $promise = Stream\first($stream);

        $stream->close();

        $this->expectPromiseReject($promise);
    }

    public function testClosingStreamResolvesWhenWaitingForCloseEvent()
    {
        $stream = new ReadableStream();
        $promise = Stream\first($stream, 'close');

        $stream->close();

        $this->expectPromiseResolve($promise);
    }

    public function testEmittingDataOnStreamResolvesWithFirstEvent()
    {
        $stream = new ReadableStream();
        $promise = Stream\first($stream);

        $stream->emit('data', array('hello', $stream));
        $stream->emit('data', array('world', $stream));
        $stream->close();

        $this->expectPromiseResolveWith('hello', $promise);
    }

    public function testEmittingErrorOnStreamDoesNothing()
    {
        $stream = new ReadableStream();
        $promise = Stream\first($stream);

        $stream->emit('error', array(new \RuntimeException('test')));

        $promise->then($this->expectCallableNever(), $this->expectCallableNever());
    }

    public function testEmittingErrorResolvesWhenWaitingForErrorEvent()
    {
        $stream = new ReadableStream();
        $promise = Stream\first($stream, 'error');

        $stream->emit('error', array(new \RuntimeException('test')));

        $this->expectPromiseResolve($promise);
    }

    public function testCancelPendingStreamWillReject()
    {
        $stream = new ReadableStream();

        $promise = Stream\first($stream);

        $promise->cancel();

        $this->expectPromiseReject($promise);
    }
}
