<?php

namespace React\Tests\Promise\Stream;

use React\Promise;
use React\Promise\Deferred;
use React\Promise\Stream;
use React\Stream\ThroughStream;

class UnwrapReadableTest extends TestCase
{
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

        $stream->on('close', $this->expectCallableOnce());
        $stream->on('end', $this->expectCallableNever());
        $stream->on('error', $this->expectCallableNever());

        $stream->close();

        $this->assertFalse($stream->isReadable());
    }

    public function testClosingRejectingStreamMakesItNotReadable()
    {
        $deferred = new Deferred();

        $stream = Stream\unwrapReadable($deferred->promise());

        $stream->on('close', $this->expectCallableOnce());
        $stream->on('end', $this->expectCallableNever());
        $stream->on('error', $this->expectCallableNever());

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
        $deferred = new Deferred();

        $stream = Stream\unwrapReadable($deferred->promise());

        $this->assertTrue($stream->isReadable());

        $stream->on('error', $this->expectCallableOnce());
        $stream->on('end', $this->expectCallableNever());

        $deferred->reject(new \RuntimeException());

        $this->assertFalse($stream->isReadable());
    }

    public function testEmitsErrorWhenPromiseResolvesWithWrongValue()
    {
        $deferred = new Deferred();

        $stream = Stream\unwrapReadable($deferred->promise());

        $this->assertTrue($stream->isReadable());

        $stream->on('error', $this->expectCallableOnce());
        $stream->on('end', $this->expectCallableNever());

        $deferred->resolve(42);

        $this->assertFalse($stream->isReadable());
    }

    public function testReturnsClosedStreamIfInputStreamIsClosed()
    {
        $input = new ThroughStream();
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
        $input = new ThroughStream();
        $input->close();

        $deferred = new Deferred();

        $stream = Stream\unwrapReadable($deferred->promise());

        $this->assertTrue($stream->isReadable());

        $stream->on('close', $this->expectCallableOnce());

        $deferred->resolve($input);

        $this->assertFalse($stream->isReadable());
    }

    public function testEmitsDataWhenInputEmitsData()
    {
        $input = new ThroughStream();

        $promise = Promise\resolve($input);
        $stream = Stream\unwrapReadable($promise);

        $stream->on('data', $this->expectCallableOnceWith('hello world'));
        $input->emit('data', array('hello world'));
    }

    public function testEmitsErrorAndClosesWhenInputEmitsError()
    {
        $input = new ThroughStream();

        $promise = Promise\resolve($input);
        $stream = Stream\unwrapReadable($promise);

        $stream->on('error', $this->expectCallableOnceWith(new \RuntimeException()));
        $stream->on('close', $this->expectCallableOnce());
        $input->emit('error', array(new \RuntimeException()));

        $this->assertFalse($stream->isReadable());
    }

    public function testEmitsEndAndClosesWhenInputEmitsEnd()
    {
        $input = new ThroughStream();

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

        $stream->on('end', $this->expectCallableNever());
        $stream->on('close', $this->expectCallableOnce());

        $stream->close();
        $stream->close();
    }

    public function testForwardsPauseToInputStream()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $input->expects($this->once())->method('pause');

        $promise = Promise\resolve($input);
        $stream = Stream\unwrapReadable($promise);

        $stream->pause();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testPauseAfterCloseHasNoEffect()
    {
        $promise = new \React\Promise\Promise(function () { });
        $stream = Stream\unwrapReadable($promise);

        $stream->close();
        $stream->pause();
    }


    /**
     * @doesNotPerformAssertions
     */
    public function testPauseAfterErrorDueToInvalidInputHasNoEffect()
    {
        $promise = \React\Promise\reject(new \RuntimeException());
        $stream = Stream\unwrapReadable($promise);

        $stream->pause();
    }

    public function testForwardsResumeToInputStream()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $input->expects($this->once())->method('resume');

        $promise = Promise\resolve($input);
        $stream = Stream\unwrapReadable($promise);

        $stream->resume();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testResumeAfterCloseHasNoEffect()
    {
        $promise = new \React\Promise\Promise(function () { });
        $stream = Stream\unwrapReadable($promise);

        $stream->close();
        $stream->resume();
    }

    public function testPipingStreamWillForwardDataEvents()
    {
        $input = new ThroughStream();

        $promise = Promise\resolve($input);
        $stream = Stream\unwrapReadable($promise);

        $output = new ThroughStream();
        $outputPromise = Stream\buffer($output);
        $stream->pipe($output);

        $input->emit('data', array('hello'));
        $input->emit('data', array('world'));
        $input->end();

        $outputPromise->then($this->expectCallableOnceWith('helloworld'));
    }

    public function testClosingStreamWillCloseInputStream()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $input->expects($this->once())->method('isReadable')->willReturn(true);
        $input->expects($this->once())->method('close');

        $promise = Promise\resolve($input);
        $stream = Stream\unwrapReadable($promise);

        $stream->close();
    }

    public function testClosingStreamWillCloseStreamIfItIgnoredCancellationAndResolvesLater()
    {
        $input = new ThroughStream();

        $deferred = new Deferred();

        $stream = Stream\unwrapReadable($deferred->promise());

        $stream->on('close', $this->expectCallableOnce());

        $stream->close();

        $deferred->resolve($input);

        $this->assertFalse($input->isReadable());
    }

    public function testClosingStreamWillCloseStreamFromCancellationHandler()
    {
        $input = new ThroughStream();

        $promise = new \React\Promise\Promise(function () { }, function ($resolve) use ($input) {
            $resolve($input);
        });

        $stream = Stream\unwrapReadable($promise);

        $stream->on('close', $this->expectCallableOnce());

        $stream->close();

        $this->assertFalse($input->isReadable());
    }

    public function testCloseShouldRemoveAllListenersAfterCloseEvent()
    {
        $promise = new \React\Promise\Promise(function () { });
        $stream = Stream\unwrapReadable($promise);

        $stream->on('close', $this->expectCallableOnce());
        $this->assertCount(1, $stream->listeners('close'));

        $stream->close();

        $this->assertCount(0, $stream->listeners('close'));
    }

    public function testCloseShouldRemoveReferenceToPromiseToAvoidGarbageReferences()
    {
        $promise = new \React\Promise\Promise(function () { });
        $stream = Stream\unwrapReadable($promise);

        $stream->close();

        $ref = new \ReflectionProperty($stream, 'promise');
        $ref->setAccessible(true);
        $value = $ref->getValue($stream);

        $this->assertNull($value);
    }
}
