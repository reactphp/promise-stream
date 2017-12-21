<?php

namespace React\Tests\Promise\Stream;

use Clue\React\Block;
use React\EventLoop\Factory;
use React\Promise;
use React\Promise\Stream;
use React\Promise\Timer;
use React\Stream\ThroughStream;

class UnwrapWritableTest extends TestCase
{
    private $loop;

    public function setUp()
    {
        $this->loop = Factory::create();
    }

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
        $promise = Timer\reject(0.001, $this->loop);
        $stream = Stream\unwrapWritable($promise);

        $stream->on('close', $this->expectCallableOnce());
        $stream->on('error', $this->expectCallableNever());

        $stream->close();
        $this->loop->run();

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
        $promise = Timer\reject(0.001, $this->loop);

        $stream = Stream\unwrapWritable($promise);

        $this->assertTrue($stream->isWritable());

        $stream->on('error', $this->expectCallableOnce());
        $stream->on('close', $this->expectCallableOnce());

        $this->loop->run();

        $this->assertFalse($stream->isWritable());
    }

    public function testEmitsErrorWhenPromiseResolvesWithWrongValue()
    {
        $promise = Timer\resolve(0.001, $this->loop);

        $stream = Stream\unwrapWritable($promise);

        $this->assertTrue($stream->isWritable());

        $stream->on('error', $this->expectCallableOnce());
        $stream->on('close', $this->expectCallableOnce());

        $this->loop->run();

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

        $promise = Timer\resolve(0.001, $this->loop)->then(function () use ($input) {
            return $input;
        });

        $stream = Stream\unwrapWritable($promise);

        $this->assertTrue($stream->isWritable());

        $stream->on('close', $this->expectCallableOnce());

        $this->loop->run();

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

    public function testForwardsDataInOneGoOncePromiseResolves()
    {
        $input = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $input->expects($this->once())->method('isWritable')->willReturn(true);
        $input->expects($this->once())->method('write')->with('helloworld');
        $input->expects($this->never())->method('end');

        $promise = Timer\resolve(0.001, $this->loop)->then(function () use ($input) {
            return $input;
        });
        $stream = Stream\unwrapWritable($promise);

        $stream->write('hello');
        $stream->write('world');

        $this->loop->run();
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
        $input->expects($this->once())->method('write')->with('helloworld!');
        $input->expects($this->once())->method('end');

        $promise = Timer\resolve(0.001, $this->loop)->then(function () use ($input) {
            return $input;
        });
        $stream = Stream\unwrapWritable($promise);

        $stream->write('hello');
        $stream->write('world');
        $stream->end('!');

        $this->loop->run();
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

        $promise = Timer\resolve(0.001, $this->loop)->then(function () use ($input) {
            return $input;
        });
        $stream = Stream\unwrapWritable($promise);

        $stream->end();
        $stream->write('nope');

        $this->loop->run();
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
        $this->markTestIncomplete();

        $input = new ThroughStream();

        $loop = $this->loop;
        $promise = new Promise\Promise(function ($resolve) use ($loop, $input) {
            $loop->addTimer(0.001, function () use ($resolve, $input) {
                $resolve($input);
            });
        });

        $stream = Stream\unwrapReadable($promise);

        $stream->on('close', $this->expectCallableOnce());

        $stream->close();

        Block\await($promise, $this->loop);

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
}
