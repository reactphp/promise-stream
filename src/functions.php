<?php

namespace React\Promise\Stream;

use Evenement\EventEmitterInterface;
use React\Promise;
use React\Promise\PromiseInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;

/**
 * Creates a `Promise` which resolves with the stream data buffer
 *
 * @param ReadableStreamInterface $stream
 * @param int|null $maxLength Maximum number of bytes to buffer or null for unlimited.
 * @return Promise\CancellablePromiseInterface Promise<string, Exception>
 */
function buffer(ReadableStreamInterface $stream, $maxLength = null)
{
    // stream already ended => resolve with empty buffer
    if (!$stream->isReadable()) {
        return Promise\resolve('');
    }

    $buffer = '';

    $promise = new Promise\Promise(function ($resolve, $reject) use ($stream, $maxLength, &$buffer, &$bufferer) {
        $bufferer = function ($data) use (&$buffer, $reject, $maxLength) {
            $buffer .= $data;

            if ($maxLength !== null && isset($buffer[$maxLength])) {
                $reject(new \OverflowException('Buffer exceeded maximum length'));
            }
        };

        $stream->on('data', $bufferer);

        $stream->on('error', function ($error) use ($reject) {
            $reject(new \RuntimeException('An error occured on the underlying stream while buffering', 0, $error));
        });

        $stream->on('close', function () use ($resolve, &$buffer) {
            $resolve($buffer);
        });
    }, function ($_, $reject) {
        $reject(new \RuntimeException('Cancelled buffering'));
    });

    return $promise->then(null, function ($error) use (&$buffer, $bufferer, $stream) {
        // promise rejected => clear buffer and buffering
        $buffer = '';
        $stream->removeListener('data', $bufferer);

        throw $error;
    });
}

/**
 * Creates a `Promise` which resolves with the first event data
 *
 * @param ReadableStreamInterface|WritableStreamInterface $stream
 * @param string                                          $event
 * @return Promise\CancellablePromiseInterface Promise<mixed, Exception>
 */
function first(EventEmitterInterface $stream, $event = 'data')
{
    if ($stream instanceof ReadableStreamInterface) {
        // readable or duplex stream not readable => already closed
        // a half-open duplex stream is considered closed if its readable side is closed
        if (!$stream->isReadable()) {
            return Promise\reject(new \RuntimeException('Stream already closed'));
        }
    } elseif ($stream instanceof WritableStreamInterface) {
        // writable-only stream (not duplex) not writable => already closed
        if (!$stream->isWritable()) {
            return Promise\reject(new \RuntimeException('Stream already closed'));
        }
    }

    return new Promise\Promise(function ($resolve, $reject) use ($stream, $event, &$listener) {
        $listener = function ($data = null) use ($stream, $event, &$listener, $resolve) {
            $stream->removeListener($event, $listener);
            $resolve($data);
        };
        $stream->on($event, $listener);

        if ($event !== 'error') {
            $stream->on('error', function ($error) use ($stream, $event, $listener, $reject) {
                $stream->removeListener($event, $listener);
                $reject(new \RuntimeException('An error occured on the underlying stream while waiting for event', 0, $error));
            });
        }

        $stream->on('close', function () use ($stream, $event, $listener, $reject) {
            $stream->removeListener($event, $listener);
            $reject(new \RuntimeException('Stream closed'));
        });
    }, function ($_, $reject) use ($stream, $event, &$listener) {
        $stream->removeListener($event, $listener);
        $reject(new \RuntimeException('Operation cancelled'));
    });
}

/**
 * Creates a `Promise` which resolves with an array of all the event data
 *
 * @param ReadableStreamInterface|WritableStreamInterface $stream
 * @param string                                          $event
 * @return Promise\CancellablePromiseInterface Promise<string, Exception>
 */
function all(EventEmitterInterface $stream, $event = 'data')
{
    // stream already ended => resolve with empty buffer
    if ($stream instanceof ReadableStreamInterface) {
        // readable or duplex stream not readable => already closed
        // a half-open duplex stream is considered closed if its readable side is closed
        if (!$stream->isReadable()) {
            return Promise\resolve(array());
        }
    } elseif ($stream instanceof WritableStreamInterface) {
        // writable-only stream (not duplex) not writable => already closed
        if (!$stream->isWritable()) {
            return Promise\resolve(array());
        }
    }

    $buffer = array();
    $bufferer = function ($data = null) use (&$buffer) {
        $buffer []= $data;
    };
    $stream->on($event, $bufferer);

    $promise = new Promise\Promise(function ($resolve, $reject) use ($stream, &$buffer) {
        $stream->on('error', function ($error) use ($reject) {
            $reject(new \RuntimeException('An error occured on the underlying stream while buffering', 0, $error));
        });

        $stream->on('close', function () use ($resolve, &$buffer) {
            $resolve($buffer);
        });
    }, function ($_, $reject) {
        $reject(new \RuntimeException('Cancelled buffering'));
    });

    return $promise->then(null, function ($error) use (&$buffer, $bufferer, $stream, $event) {
        // promise rejected => clear buffer and buffering
        $buffer = array();
        $stream->removeListener($event, $bufferer);

        throw $error;
    });
}

/**
 * unwrap a `Promise` which resolves with a `ReadableStreamInterface`.
 *
 * @param PromiseInterface $promise Promise<ReadableStreamInterface, Exception>
 * @return ReadableStreamInterface
 */
function unwrapReadable(PromiseInterface $promise)
{
    return new UnwrapReadableStream($promise);
}

/**
 * unwrap a `Promise` which resolves with a `WritableStreamInterface`.
 *
 * @param PromiseInterface $promise Promise<WritableStreamInterface, Exception>
 * @return WritableStreamInterface
 */
function unwrapWritable(PromiseInterface $promise)
{
    return new UnwrapWritableStream($promise);
}
