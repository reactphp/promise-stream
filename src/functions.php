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
    $out = new ReadableStream();

    // TODO: support backpressure

    // try to cancel promise once the stream closes
    if ($promise instanceof CancellablePromiseInterface) {
        $out->on('close', function() use ($promise) {
            $promise->cancel();
        });
    }

    $promise->then(
        function ($stream) {
            if (!($stream instanceof ReadableStreamInterface)) {
                throw new \InvalidArgumentException('Not a readable stream');
            }
            return $stream;
        }
    )->then(
        function (ReadableStreamInterface $stream) use ($out) {
            if (!$stream->isReadable()) {
                $out->close();
                return;
            }

            // stream any writes into output stream
            $stream->on('data', function ($data) use ($out) {
                $out->emit('data', array($data, $out));
            });

            // error events cancel output stream
            $stream->on('error', function ($error) use ($out) {
                $out->emit('error', array($error, $out));
                $out->close();
            });

            // close output stream once body closes
            $stream->on('close', function () use ($out) {
                $out->close();
            });
            $stream->on('end', function () use ($out) {
                $out->close();
            });
        },
        function ($e) use ($out) {
            $out->emit('error', array($e, $out));
            $out->close();
        }
    );

    return $out;
}
