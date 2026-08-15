# CakePHP Audit Plugin

The **Audit** plugin diagnoses common configuration, environment, and infrastructure problems in your CakePHP applications through a suite of independent checks. Each diagnostic inspects a single concern — such as runtime directory permissions, the application secret, queue connections, or debug mode — and reports a status with a safe deterministic fix when one is available. Audit runs from the console, integrates with the queue, migration, and scheduling plugins, and supports human-readable, JSON, GitHub Actions, and agent-optimized report formats.

## Requirements

* PHP 8.3+

See [Versions.md](docs/Versions.md) for the supported CakePHP versions.

## Documentation

For documentation, as well as tutorials, see the [docs](docs/index.md) directory of this repository.

## License

Licensed under the [MIT](http://www.opensource.org/licenses/mit-license.php) License. Redistributions of the source code included in this repository must retain the copyright notice found in each file.
