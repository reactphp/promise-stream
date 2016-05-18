<?php

namespace Clue\React\Promise\Stream;

use React\Stream\ReadableStreamInterface;
use React\Promise;
use React\Promise\PromiseInterface;

/**
 * Creates a `Promise` which resolves with the stream data buffer
 *
 * @param ReadableStreamInterface $stream
 * @return CancellablePromiseInterface Promise<string, Exception>
 */
function buffer(ReadableStreamInterface $stream)
{
    // stream already ended => resolve with empty buffer
    if (!$stream->isReadable()) {
        return Promise\resolve('');
    }

    $buffer = '';
    $bufferer = function ($data) use (&$buffer) {
        $buffer .= $data;
    };
    $stream->on('data', $bufferer);

    $promise = new Promise\Promise(function ($resolve, $reject) use ($stream, &$buffer) {
        $stream->on('error', function ($error) use ($reject) {
            $reject(new \RuntimeException('An error occured on the underlying stream while buffering', 0, $error));
        });

        $stream->on('close', function () use ($resolve, &$buffer) {
            $resolve($buffer);
        });
    }, function ($_, $reject) use ($buffer) {
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
 * @param ReadableStreamInterface $stream
 * @param string $event
 * @return CancellablePromiseInterface Promise<mixed, Exception>
 */
function first(ReadableStreamInterface $stream, $event = 'data')
{
    // stream already ended => reject with error
    if (!$stream->isReadable()) {
        return Promise\reject(new \RuntimeException('Stream already closed'));
    }

    return new Promise\Promise(function ($resolve, $reject) use ($stream, $event, &$listener) {
        $listener = function ($data) use ($stream, $event, &$listener, $resolve) {
            $stream->removeListener($event, $listener);
            $resolve($data);
        };
        $stream->on($event, $listener);

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
 * unwrap a `Promise` which resolves with a `ReadableStreamInterface`.
 *
 * @param PromiseInterface $promise Promise<ReadableStreamInterface, Exception>
 * @return ReadableStreamInterface
 */
function unwrapReadable(PromiseInterface $promise)
{
    return new UnwrapReadableStream($promise);
}
