# Changelog

## [0.10.2](https://github.com/BambooHR/guardrail/compare/v0.10.1...v0.10.2) (2026-08-13)


### Bug Fixes

* Remove no-dev from composer install to see if integration tests pass ([aed33ab](https://github.com/BambooHR/guardrail/commit/aed33ab4692deb5abd73f7a516ec1ccd5b813fce))

## [0.10.1](https://github.com/BambooHR/guardrail/compare/v0.10.0...v0.10.1) (2026-08-12)


### Bug Fixes

* Uses composer.json to add version info ([#178](https://github.com/BambooHR/guardrail/issues/178)) ([42791fa](https://github.com/BambooHR/guardrail/commit/42791faa2e19d046e830444aef4afc68b7aded47))

## [0.10.0](https://github.com/BambooHR/guardrail/compare/v0.9.8...v0.10.0) (2026-08-11)


### Features

* add cases() method to enums AST and test static call validation ([f99f0ed](https://github.com/BambooHR/guardrail/commit/f99f0ed9d3b52aab9407ef98b375885401185b13))
* add cases() method to enums AST and test static call validation ([3ab075e](https://github.com/BambooHR/guardrail/commit/3ab075e289afbd78f48b5c76d1ffc496469d86de))
* add PHP attribute validation with target, repeatability, and constructor checks ([e1836a2](https://github.com/BambooHR/guardrail/commit/e1836a2bbab6c662c3e685d61aaf90add4857901))
* add release-please with phar release upload ([7f0bb8e](https://github.com/BambooHR/guardrail/commit/7f0bb8e251ed5cd3696ae0f2b339a1a755a048d9))
* add static modifier to enum cases() method and update related tests ([205512e](https://github.com/BambooHR/guardrail/commit/205512edc5738d93a3ebe3144c055baf9f45928b))
* **check:** implements GlobalFunctionCheck ([#130](https://github.com/BambooHR/guardrail/issues/130)) ([0198321](https://github.com/BambooHR/guardrail/commit/01983215a0dc182affb85298408964cc6a38fe6c))
* collection emptiness check ([dfc58c2](https://github.com/BambooHR/guardrail/commit/dfc58c218ba19949213f1ea57dcd4c3a4da99789))
* collection emptiness check ([10bb974](https://github.com/BambooHR/guardrail/commit/10bb9746e22efec873b6cb5f855a11f8bc19e417))
* implement attribute validation with constant expression evaluation ([6a567cf](https://github.com/BambooHR/guardrail/commit/6a567cf641919d7cbfcae90f7ceac6bb4c26d2da))


### Bug Fixes

* don't false-positive unused vars used in arrow fn ternary ([2d293f1](https://github.com/BambooHR/guardrail/commit/2d293f143eb75a028f39c11650893a6f5dc876f8))
* don't false-positive unused vars used in arrow fn ternary ([2ed2cc9](https://github.com/BambooHR/guardrail/commit/2ed2cc91c5108b84bfba246d2b05091c9036fe05))
* make phar release upload idempotent on re-run ([7f8d269](https://github.com/BambooHR/guardrail/commit/7f8d26916658c25359970ea2ae4e5e43cc2be151))
* resolve attribute name resolution and class constant access issues ([519bd6e](https://github.com/BambooHR/guardrail/commit/519bd6e124198ecd5043578411609f67c60efdf4))
* scope job permissions and checksum the release phar ([37a15f9](https://github.com/BambooHR/guardrail/commit/37a15f9adca3bbbab7ce6324146a7c64a91a3e6b))
* **SPEED-2558:** Fix error reporting to properly ignore suppressed errors ([8e1665e](https://github.com/BambooHR/guardrail/commit/8e1665ef3036362b4e11c9a187e3f6bd1e53e0dc))
* **SPEED-2558:** Fix error reporting to properly ignore suppressed errors ([794c155](https://github.com/BambooHR/guardrail/commit/794c1556737bf063508b58e1966e7a253ca8fae4))
* stop synthesizing a values() method onto backed enums ([572a727](https://github.com/BambooHR/guardrail/commit/572a727073700cc3b2ceb360cd9175bce417a1c6))
* stop synthesizing a values() method onto backed enums ([14543b1](https://github.com/BambooHR/guardrail/commit/14543b1d44dcf2dcd9dd12f3878e29b4f3b35611))
* use php release-type so composer.json version gets bumped ([24b951c](https://github.com/BambooHR/guardrail/commit/24b951c6452750b68c0b2bdf6fa9943e02f1962e))


### Miscellaneous Chores

* add techdocs pipeline (SPEED-3253) ([558d17f](https://github.com/BambooHR/guardrail/commit/558d17f41e46d89b546d3a9feef570e23f0f481b))
* code coverage for ClassConstantCheck.php ([95387bb](https://github.com/BambooHR/guardrail/commit/95387bb1cf978bcc7f244435190d851251daccbf))
* code coverage for ClassMethodStringCheck.php ([0f58b5f](https://github.com/BambooHR/guardrail/commit/0f58b5fe4219a07bfb31bed6011ec32227390596))
* code coverage for ClassStoredAsVariableCheck.php ([97432de](https://github.com/BambooHR/guardrail/commit/97432de6525848b0a98eb8da5076415c6e00f732))
* code coverage for ConstructorCheck, DefinedConstantCheck, ConditionalAssignmentCheck ([b9bba79](https://github.com/BambooHR/guardrail/commit/b9bba79dd683b9af2c500aa9b421421fa4066af3))
* fix warnings ([5478e43](https://github.com/BambooHR/guardrail/commit/5478e4320e126a9c1a405cf55ae0aa600cb68f7f))
* improve DefinedConstantCheck test ([f189963](https://github.com/BambooHR/guardrail/commit/f1899635a557fb143b5e21f632765b38b57bce09))
* readme grammar ([2beb36b](https://github.com/BambooHR/guardrail/commit/2beb36b405c8d0931d3cf5271584d9420c566ba9))
* **SPEED-3498:** add release-please with phar release upload ([20397be](https://github.com/BambooHR/guardrail/commit/20397beba2dca45e1335b09d5ce985133e47e0df))
* test coverage for DependenciesOnVendorCheck, DocBlockTypesCheck, and EnumCheck ([acddb93](https://github.com/BambooHR/guardrail/commit/acddb93488653d4e77b8444957c06cb945b22b37))
* test coverage for FunctionCallCheck, GlobalFunctionCheck, GotoCheck, ImagickCheck ([5ca3a05](https://github.com/BambooHR/guardrail/commit/5ca3a053ddbd77358b02bc3079f6f0171c8060cd))
