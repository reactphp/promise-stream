<?php

namespace React\Tests\Promise\Stream;

use React\Promise;
use React\Promise\Deferred;
use React\Promise\Stream;
use React\Stream\ThroughStream;

class UnwrapWritableTest extends TestCase
{
    public function testReturnsWritableStreamForPromise()
    {
        $promise = new \React\Promise\Promise(function () { });
        $stream = Stream\unwrapWritable($promise);

        $this->assertTrue($stream->isWritable());
    }

    public function testClosingStreamMakesItNotWritable()
    {
        $promise = new \React\Promise\Promise(function () { });
        $stream = Stream\unwrapWritable($promise);

        $stream->on('close', $this->expectCallableOnce());
        $stream->on('error', $this->expectCallableNever());

        $stream->close();

        $this->assertFalse($stream->isWritable());
    }

    public function testClosingRejectingStreamMakesItNotWritable()
    {
        $deferred = new Deferred();

        $stream = Stream\unwrapWritable($deferred->promise());

        $stream->on('close', $this->expectCallableOnce());
        $stream->on('error', $this->expectCallableNever());

        $stream->close();

        $this->assertFalse($stream->isWritable());
    }

    public function testClosingStreamWillCancelInputPromiseAndMakeStreamNotWritable()
    {
        $promise = new \React\Promise\Promise(function () { }, $this->expectCallableOnce());
        $stream = Stream\unwrapWritable($promise);

        $stream->close();

        $this->assertFalse($stream->isWritable());
    }

    public function testEmitsErrorWhenPromiseRejects()
    {
        $deferred = new Deferred();

        $stream = Stream\unwrapWritable($deferred->promise());

        $this->assertTrue($stream->isWritable());

        $stream->on('error', $this->expectCallableOnce());
        $stream->on('close', $this->expectCallableOnce());

        $deferred->reject(new \RuntimeException());

        $this->assertFalse($stream->isWritable());
    }

    public function testEmitsErrorWhenPromiseResolvesWithWrongValue()
    {
        $deferred = new Deferred();

        $stream = Stream\unwrapWritable($deferred->promise());

        $this->assertTrue($stream->isWritable());

        $stream->on('error', $this->expectCallableOnce());
        $stream->on('close', $this->expectCallableOnce());

        $deferred->resolve(42);

        $this->assertFalse($stream->isWritable());
    }

    public function testReturnsClosedStreamIfInputStreamIsClosed()
    {
        $input = new ThroughStream();
        $input->close();

        $promise = Promise\resolve($input);

        $stream = Stream\unwrapWritable($promise);

        $this->assertFalse($stream->isWritable());
    }

    public function testReturnsClosedStreamIfInputHasWrongValue()
    {
        $promise = Promise\resolve(42);

        $stream = Stream\unwrapWritable($promise);

        $this->assertFalse($stream->isWritable());
    }

    public function testReturnsStreamThatWillBeClosedWhenPromiseResolvesWithClosedInputStream()
    {
        $input = new ThroughStream();
        $input->close();

        $deferred = new Deferred();

        $stream = Stream\unwrapWritable($deferred->promise());

        $this->assertTrue($stream->isWritable());

        $stream->on('close', $this->expectCallableOnce());

        $deferred->resolve($input);

        $this->assertFalse($stream->isWritable());
    }

    public function testForwardsDataImmediatelyIfPromiseIsAlreadyResolved()
    {
        $input = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $input->expects($this->once())->method('isWritable')->willReturn(true);
        $input->expects($this->once())->method('write')->with('hello');
        $input->expects($this->never())->method('end');

        $promise = Promise\resolve($input);
        $stream = Stream\unwrapWritable($promise);

        $stream->write('hello');
    }

    public function testForwardsOriginalDataOncePromiseResolves()
    {
        $data = new \stdClass();

        $input = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $input->expects($this->once())->method('isWritable')->willReturn(true);
        $input->expects($this->once())->method('write')->with($data);
        $input->expects($this->never())->method('end');

        $deferred = new Deferred();

        $stream = Stream\unwrapWritable($deferred->promise());

        $stream->write($data);

        $deferred->resolve($input);
    }

    public function testForwardsDataInOriginalChunksOncePromiseResolves()
    {
        $input = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $input->expects($this->once())->method('isWritable')->willReturn(true);
        $input->expects($this->exactly(2))->method('write')->withConsecutive(array('hello'), array('world'));
        $input->expects($this->never())->method('end');

        $deferred = new Deferred();

        $stream = Stream\unwrapWritable($deferred->promise());

        $stream->write('hello');
        $stream->write('world');

        $deferred->resolve($input);
    }

    public function testForwardsDataAndEndImmediatelyIfPromiseIsAlreadyResolved()
    {
        $input = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $input->expects($this->once())->method('isWritable')->willReturn(true);
        $input->expects($this->once())->method('write')->with('hello');
        $input->expects($this->once())->method('end')->with('!');

        $promise = Promise\resolve($input);
        $stream = Stream\unwrapWritable($promise);

        $stream->write('hello');
        $stream->end('!');
    }

    public function testForwardsDataAndEndOncePromiseResolves()
    {
        $input = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $input->expects($this->once())->method('isWritable')->willReturn(true);
        $input->expects($this->exactly(3))->method('write')->withConsecutive(array('hello'), array('world'), array('!'));
        $input->expects($this->once())->method('end');

        $deferred = new Deferred();

        $stream = Stream\unwrapWritable($deferred->promise());

        $stream->write('hello');
        $stream->write('world');
        $stream->end('!');

        $deferred->resolve($input);
    }

    public function testForwardsNoDataWhenWritingAfterEndIfPromiseIsAlreadyResolved()
    {
        $input = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $input->expects($this->once())->method('isWritable')->willReturn(true);
        $input->expects($this->never())->method('write');
        $input->expects($this->once())->method('end');

        $promise = Promise\resolve($input);
        $stream = Stream\unwrapWritable($promise);

        $stream->end();
        $stream->end();
        $stream->write('nope');
    }

    public function testForwardsNoDataWhenWritingAfterEndOncePromiseResolves()
    {
        $input = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $input->expects($this->once())->method('isWritable')->willReturn(true);
        $input->expects($this->never())->method('write');
        $input->expects($this->once())->method('end');

        $deferred = new Deferred();

        $stream = Stream\unwrapWritable($deferred->promise());

        $stream->end();
        $stream->write('nope');

        $deferred->resolve($input);
    }

    public function testWriteReturnsFalseWhenPromiseIsPending()
    {
        $promise = new \React\Promise\Promise(function () { });
        $stream = Stream\unwrapWritable($promise);

        $ret = $stream->write('nope');

        $this->assertFalse($ret);
    }

    public function testWriteReturnsTrueWhenUnwrappedStreamReturnsTrueForWrite()
    {
        $input = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $input->expects($this->once())->method('isWritable')->willReturn(true);
        $input->expects($this->once())->method('write')->willReturn(true);

        $promise = \React\Promise\resolve($input);
        $stream = Stream\unwrapWritable($promise);

        $ret = $stream->write('hello');

        $this->assertTrue($ret);
    }

    public function testWriteReturnsFalseWhenUnwrappedStreamReturnsFalseForWrite()
    {
        $input = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $input->expects($this->once())->method('isWritable')->willReturn(true);
        $input->expects($this->once())->method('write')->willReturn(false);

        $promise = \React\Promise\resolve($input);
        $stream = Stream\unwrapWritable($promise);

        $ret = $stream->write('nope');

        $this->assertFalse($ret);
    }

    public function testWriteAfterCloseReturnsFalse()
    {
        $promise = new \React\Promise\Promise(function () { });
        $stream = Stream\unwrapWritable($promise);

        $stream->close();
        $ret = $stream->write('nope');

        $this->assertFalse($ret);
    }

    public function testEmitsErrorAndClosesWhenInputEmitsError()
    {
        $input = new ThroughStream();

        $promise = Promise\resolve($input);
        $stream = Stream\unwrapWritable($promise);

        $stream->on('error', $this->expectCallableOnceWith(new \RuntimeException()));
        $stream->on('close', $this->expectCallableOnce());
        $input->emit('error', array(new \RuntimeException()));

        $this->assertFalse($stream->isWritable());
    }

    public function testEmitsDrainWhenPromiseResolvesWithStreamWhenForwardingData()
    {
        $input = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $input->expects($this->once())->method('isWritable')->willReturn(true);
        $input->expects($this->once())->method('write')->with('hello')->willReturn(true);

        $deferred = new Deferred();
        $stream = Stream\unwrapWritable($deferred->promise());
        $stream->write('hello');

        $stream->on('drain', $this->expectCallableOnce());
        $deferred->resolve($input);
    }

    public function testDoesNotEmitDrainWhenStreamBufferExceededAfterForwardingData()
    {
        $input = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $input->expects($this->once())->method('isWritable')->willReturn(true);
        $input->expects($this->once())->method('write')->with('hello')->willReturn(false);

        $deferred = new Deferred();
        $stream = Stream\unwrapWritable($deferred->promise());
        $stream->write('hello');

        $stream->on('drain', $this->expectCallableNever());
        $deferred->resolve($input);
    }

    public function testEmitsDrainWhenInputEmitsDrain()
    {
        $input = new ThroughStream();

        $promise = Promise\resolve($input);
        $stream = Stream\unwrapWritable($promise);

        $stream->on('drain', $this->expectCallableOnce());
        $input->emit('drain', array());
    }

    public function testEmitsCloseOnlyOnceWhenClosingStreamMultipleTimes()
    {
        $promise = new Promise\Promise(function () { });
        $stream = Stream\unwrapWritable($promise);

        $stream->on('close', $this->expectCallableOnce());
        $stream->on('error', $this->expectCallableNever());

        $stream->close();
        $stream->close();
    }

    public function testClosingStreamWillCloseInputStream()
    {
        $input = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $input->expects($this->once())->method('isWritable')->willReturn(true);
        $input->expects($this->once())->method('close');

        $promise = Promise\resolve($input);
        $stream = Stream\unwrapWritable($promise);

        $stream->close();
    }

    public function testClosingStreamWillCloseStreamIfItIgnoredCancellationAndResolvesLater()
    {
        $input = new ThroughStream();
        $input->on('close', $this->expectCallableOnce());

        $deferred = new Deferred();

        $stream = Stream\unwrapReadable($deferred->promise());

        $stream->on('close', $this->expectCallableOnce());

        $stream->close();

        $this->assertTrue($input->isReadable());

        $deferred->resolve($input);

        $this->assertFalse($input->isReadable());
    }

    public function testClosingStreamWillCloseStreamFromCancellationHandler()
    {
        $input = new ThroughStream();

        $promise = new \React\Promise\Promise(function () { }, function ($resolve) use ($input) {
            $resolve($input);
        });

        $stream = Stream\unwrapWritable($promise);

        $stream->on('close', $this->expectCallableOnce());

        $stream->close();

        $this->assertFalse($input->isWritable());
    }

    public function testCloseShouldRemoveAllListenersAfterCloseEvent()
    {
        $promise = new \React\Promise\Promise(function () { });
        $stream = Stream\unwrapWritable($promise);

        $stream->on('close', $this->expectCallableOnce());
        $this->assertCount(1, $stream->listeners('close'));

        $stream->close();

        $this->assertCount(0, $stream->listeners('close'));
    }

    public function testCloseShouldRemoveReferenceToPromiseAndStreamToAvoidGarbageReferences()
    {
        $promise = \React\Promise\resolve(new ThroughStream());
        $stream = Stream\unwrapWritable($promise);

        $stream->close();

        $ref = new \ReflectionProperty($stream, 'promise');
        $ref->setAccessible(true);
        $value = $ref->getValue($stream);

        $this->assertNull($value);

        $ref = new \ReflectionProperty($stream, 'stream');
        $ref->setAccessible(true);
        $value = $ref->getValue($stream);

        $this->assertNull($value);
    }
}
