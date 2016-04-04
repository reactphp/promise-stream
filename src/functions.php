<?php

namespace Clue\React\Promise\Stream;

use React\Stream\ReadableStream;
use React\Stream\ReadableStreamInterface;
use React\Promise;
use React\Promise\PromiseInterface;
use React\Promise\CancellablePromiseInterface;

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
 * unwrap a `Promise` which resolves with a `ReadableStreamInterface`.
 *
 * @param PromiseInterface $promise Promise<ReadableStreamInterface, Exception>
 * @return ReadableStreamInterface
 */
function unwrapReadable(PromiseInterface $promise)
{
    return new UnwrapReadableStream($promise);
}
