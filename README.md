# Git release manager

### Install

### Usage

#### Open new Feature

./bin/git-release-man git:feature open

#### Close Feature

Removes feature branch from remote repository

./bin/git-release-man git:feature close

#### Reopen Feature

Removes labels from pull request. It exclude feature from builds.

./bin/git-release-man git:feature reopen

#### List available features

./bin/git-release-man git:feature list

#### Mark Feature ready for testing

./bin/git-release-man git:feature test

#### Mark Feature ready for release

./bin/git-release-man git:feature release

#### Create test release (Release Candidate) Tag and Branch

./bin/git-release-man git:build test

#### Merge pull requests into master branch, create release tag

./bin/git-release-man git:build release

#### Get latest release tag

./bin/git-release-man git:build latest-release

#### Get latest test release tag

./bin/git-release-man git:build latest-test-release
