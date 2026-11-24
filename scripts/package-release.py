#!/usr/bin/env python3
"""Build an installable Moodle ZIP and SHA-256 checksum from a committed ref."""

import argparse
import hashlib
from pathlib import Path
import re
import subprocess


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("ref", nargs="?", default="HEAD")
    parser.add_argument("--output", type=Path, default=Path("dist"))
    args = parser.parse_args()
    root = Path(__file__).resolve().parents[1]
    version = subprocess.check_output(
        ["git", "show", f"{args.ref}:version.php"], cwd=root, text=True
    )
    match = re.search(r"\$plugin->release\s*=\s*'([0-9]+\.[0-9]+\.[0-9]+)'", version)
    if not match or "MATURITY_STABLE" not in version:
        parser.error("The committed ref must declare a stable semantic release version")
    output = args.output.resolve()
    output.mkdir(parents=True, exist_ok=True)
    archive = output / f"local_studystash-v{match[1]}.zip"
    subprocess.run(
        ["git", "archive", "--format=zip", "--prefix=studystash/", f"--output={archive}", args.ref],
        cwd=root,
        check=True,
    )
    digest = hashlib.sha256(archive.read_bytes()).hexdigest()
    checksum = output / f"{archive.name}.sha256"
    checksum.write_text(f"{digest}  {archive.name}\n")
    print(archive)
    print(checksum)


if __name__ == "__main__":
    main()
