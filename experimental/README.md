# Experimental Workspace

This directory is the quarantine area for local research artifacts that must not
ship in Docker images or be committed by accident:

- provider response snapshots and ad-hoc JSON exports;
- SQL dumps, `.dump` files, and local restore inputs;
- one-off debugging scripts and migration probes;
- temporary audit outputs and generated scratch files.

Only this README is tracked. If an artifact becomes part of the product contract,
move it to the correct runtime, test fixture, documentation, or migration path and
review it like production code.
