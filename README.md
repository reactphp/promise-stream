# PromiseStream

[![Build Status](https://travis-ci.org/reactphp/promise-stream.svg?branch=master)](https://travis-ci.org/reactphp/promise-stream)

The missing link between Promise-land and Stream-land
for [ReactPHP](https://reactphp.org/).

**Table of Contents**

* [Usage](#usage)
  * [buffer()](#buffer)
  * [first()](#first)
  * [all()](#all)
  * [unwrapReadable()](#unwrapreadable)
  * [unwrapWritable()](#unwrapwritable)
* [Install](#install)
* [License](#license)

## Usage

This lightweight library consists only of a few simple functions.
All functions reside under the `React\Promise\Stream` namespace.

The below examples assume you use an import statement similar to this:

```php
use React\Promise\Stream;

Stream\buffer(…);
```

Alternatively, you can also refer to them with their fully-qualified name:

```php
\React\Promise\Stream\buffer(…);
``` 

### buffer()

The `buffer(ReadableStreamInterface $stream, int $maxLength = null)` function can be used to create
a `Promise` which resolves with the stream data buffer. With an optional maximum length argument 
which defaults to no limit. In case the maximum length is reached before the end the promise will 
be rejected with a `\OverflowException`.
 

```php
$stream = accessSomeJsonStream();

Stream\buffer($stream)->then(function ($contents) {
    var_dump(json_decode($contents));
});
```

The promise will resolve with all data chunks concatenated once the stream closes.

The promise will resolve with an empty string if the stream is already closed.

The promise will reject if the stream emits an error.

The promise will reject if it is canceled.

```php
$stream = accessSomeToLargeStream();

Stream\buffer($stream, 1024)->then(function ($contents) {
    var_dump(json_decode($contents));
}, function ($error) {
    // Reaching here when the stream buffer goes above the max size,
    // in this example that is 1024 bytes,
    // or when the stream emits an error. 
});
```

### first()

The `first(ReadableStreamInterface|WritableStreamInterface $stream, $event = 'data')`
function can be used to create a `Promise` which resolves once the given event triggers for the first time.

```php
$stream = accessSomeJsonStream();

Stream\first($stream)->then(function ($chunk) {
    echo 'The first chunk arrived: ' . $chunk;
});
```

The promise will resolve with whatever the first event emitted or `null` if the
event does not pass any data.
If you do not pass a custom event name, then it will wait for the first "data"
event and resolve with a string containing the first data chunk.

The promise will reject if the stream emits an error – unless you're waiting for
the "error" event, in which case it will resolve.

The promise will reject once the stream closes – unless you're waiting for the
"close" event, in which case it will resolve.

The promise will reject if the stream is already closed.

The promise will reject if it is canceled.

### all()

The `all(ReadableStreamInterface|WritableStreamInterface $stream, $event = 'data')`
function can be used to create a `Promise` which resolves with an array of all the event data.

```php
$stream = accessSomeJsonStream();

Stream\all($stream)->then(function ($chunks) {
    echo 'The stream consists of ' . count($chunks) . ' chunk(s)';
});
```

The promise will resolve with an array of whatever all events emitted or `null` if the
events do not pass any data.
If you do not pass a custom event name, then it will wait for all the "data"
events and resolve with an array containing all the data chunks.

The promise will resolve with an array once the stream closes.

The promise will resolve with an empty array if the stream is already closed.

The promise will reject if the stream emits an error.

The promise will reject if it is canceled.

### unwrapReadable()

The `unwrapReadable(PromiseInterface $promise)` function can be used to unwrap
a `Promise` which resolves with a `ReadableStreamInterface`.

This function returns a readable stream instance (implementing `ReadableStreamInterface`)
right away which acts as a proxy for the future promise resolution.
Once the given Promise resolves with a `ReadableStreamInterface`, its data will
be piped to the output stream.

```php
//$promise = someFunctionWhichResolvesWithAStream();
$promise = startDownloadStream($uri);

$stream = Stream\unwrapReadable($promise);

$stream->on('data', function ($data) {
   echo $data;
});

$stream->on('end', function () {
   echo 'DONE';
});
```

If the given promise is either rejected or fulfilled with anything but an
instance of `ReadableStreamInterface`, then the output stream will emit
an `error` event and close:

```php
$promise = startDownloadStream($invalidUri);

$stream = Stream\unwrapReadable($promise);

$stream->on('error', function (Exception $error) {
    echo 'Error: ' . $error->getMessage();
});
```

The given `$promise` SHOULD be pending, i.e. it SHOULD NOT be fulfilled or rejected
at the time of invoking this function.
If the given promise is already settled and does not resolve with an
instance of `ReadableStreamInterface`, then you will not be able to receive
the `error` event.

You can `close()` the resulting stream at any time, which will either try to
`cancel()` the pending promise or try to `close()` the underlying stream.

```php
$promise = startDownloadStream($uri);

$stream = Stream\unwrapReadable($promise);

$loop->addTimer(2.0, function () use ($stream) {
    $stream->close();
});
```

### unwrapWritable()

The `unwrapWritable(PromiseInterface $promise)` function can be used to unwrap
a `Promise` which resolves with a `WritableStreamInterface`.

This function returns a writable stream instance (implementing `WritableStreamInterface`)
right away which acts as a proxy for the future promise resolution.
Once the given Promise resolves with a `WritableStreamInterface`, any data you
wrote to the proxy will be piped to the inner stream.

```php
//$promise = someFunctionWhichResolvesWithAStream();
$promise = startUploadStream($uri);

$stream = Stream\unwrapWritable($promise);

$stream->write('hello');
$stream->end('world');

$stream->on('close', function () {
   echo 'DONE';
});
```

If the given promise is either rejected or fulfilled with anything but an
instance of `WritableStreamInterface`, then the output stream will emit
an `error` event and close:

```php
$promise = startUploadStream($invalidUri);

$stream = Stream\unwrapWritable($promise);

$stream->on('error', function (Exception $error) {
    echo 'Error: ' . $error->getMessage();
});
```

The given `$promise` SHOULD be pending, i.e. it SHOULD NOT be fulfilled or rejected
at the time of invoking this function.
If the given promise is already settled and does not resolve with an
instance of `WritableStreamInterface`, then you will not be able to receive
the `error` event.

You can `close()` the resulting stream at any time, which will either try to
`cancel()` the pending promise or try to `close()` the underlying stream.

```php
$promise = startUploadStream($uri);

$stream = Stream\unwrapWritable($promise);

$loop->addTimer(2.0, function () use ($stream) {
    $stream->close();
});
```

## Install

The recommended way to install this library is [through Composer](https://getcomposer.org).
[New to Composer?](https://getcomposer.org/doc/00-intro.md)

This project follows [SemVer](http://semver.org/).
This will install the latest supported version:

```bash
$ composer require react/promise-stream:^1.1.1
```

See also the [CHANGELOG](CHANGELOG.md) for details about version upgrades.

This project aims to run on any platform and thus does not require any PHP
extensions and supports running on legacy PHP 5.3 through current PHP 7+ and
HHVM.
It's *highly recommended to use PHP 7+* for this project.

## License

MIT, see [LICENSE file](LICENSE).
