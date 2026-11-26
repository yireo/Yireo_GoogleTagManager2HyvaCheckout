# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.1] - 26 November 2026
### Fixed
- Escape the `begin_checkout` data layer JSON as HTML-safe, so stored data like product names can not break out of the inline script
- Only push `ga:trigger-event` details that are objects with a string `event`, and register the inline script with Hyvä CSP when available
- Stop the Magewire listeners from returning an HTTP 500 when called without a quote or payment method
- Load the quote before building the `add_shipping_info` event
- Skip pushing an event that is identical to the previous push of the same event
- Add `Magewirephp_Magewire` to the module sequence

### Changed
- Require `ext-json`

## [2.0.0] - 29 October 2026
### Changed
- Support Hyvä Checkout 3 (Magewire 3) natively, without the `hyva-themes/magento2-hyva-checkout-v1bc` layer
- Require `magewirephp/magewire` ^3.7; for Hyvä Checkout 1.x (Magewire 1) stay on 1.0.x
- Listen with `#[On]` and push events with `dispatch()`; `add_shipping_info` and `add_payment_info` carry the same data as before
- Skip the data layer push when there is no quote

## [1.0.2] - 25 October 2025
### Fixed
- Do not escape JSON

## [1.0.1] - 22 October 2025
### Fixed
- Fix after merge conflict
- Update README.md

## [1.0.0] - 06 May 2025
### Added
- Initial release
