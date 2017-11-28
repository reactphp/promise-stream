# Changelog

## 1.1.0 (2017-11-28)

* Feature: Reject `first()` when stream emits an error event
  (#7 by @clue)

* Fix: Explicit `close()` of unwrapped stream should not emit `error` event
  (#8 by @clue)

* Internal refactoring to simplify `buffer()` function
  (#6 by @kelunik)

## 1.0.0 (2017-10-24)

* First stable release, now following SemVer

> Contains no other changes, so it's actually fully compatible with the v0.1.2 release.

## 0.1.2 (2017-10-18)

* Feature: Optional maximum buffer length for `buffer()` (#3 by @WyriHaximus)
* Improvement: Readme improvements (#5 by @jsor)

## 0.1.1 (2017-05-15)

* Improvement: Forward compatibility with stream 1.0, 0.7, 0.6, and 0.5 (#2 by @WyriHaximus)

## 0.1.0 (2017-05-10)

* Initial release, adapted from [`clue/promise-stream-react`](https://github.com/clue/php-promise-stream-react)
