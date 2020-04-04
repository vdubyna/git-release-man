# Git release manager

[![Build Status](https://travis-ci.org/vdubyna/git-release-man.svg?branch=master)](https://travis-ci.org/vdubyna/git-release-man)

It is console tool which allows to create release and pre-release tags and branches in different git engines:

* github (beta)
* bitbucket (beta)
* bitlab (beta)
* local (rleased)
* remote (beta)

The workflow looks as follows:

* **Init configuration.** It can be the folder of the project or any other folder.
 The tool does not depend on the repository. It requires api token for gitlab/bitbucket/gitlab.
* **Start feature.** It creates the branch with prefix `feature/`.
* **Test feature.** It marks feature as `release candidate` and is used to compile `Release Candidate`
* **Release feature.** It marks feature as `release stable` and is used to compile `Release Stable`.
* **Create Release Candidate.** It creates `Release Candidate` branch and tag.
* **Create Release Stable.** It creates `Release Stable` branch and tag.

### Install

Go to latest release and download `git-release-man.phar`
https://github.com/vdubyna/git-release-man/releases/latest

### Usage

#### Init configuration

```bash
./git-release-man.phar g:b init
```


#### Start new Feature

```bash
./git-release-man.phar g:f start --name FEATURE_NAME_HERE
```

#### Close Feature

Removes feature branch from repository

```bash
./git-release-man.phar g:f close --name FEATURE_NAME_HERE
```

#### Reset Feature

Removes labels from feature. It exclude feature from builds.

```bash
./git-release-man.phar g:f reset --name FEATURE_NAME_HERE
```

#### List available features

```bash
./git-release-man.phar g:b features-list
```

#### Mark Feature ready for testing (release candidate)

```bash
./git-release-man.phar g:f release-candidate --name FEATURE_NAME_HERE
```

#### Mark Feature ready for release (release stable)

```bash
./git-release-man.phar g:f release-stable --name FEATURE_NAME_HERE
```

#### Create test release (Release Candidate) Tag and Branch

```bash
./git-release-man.phar g:b release-candidate
```

#### Create stable release (Release Stable) Tag and Branch

```bash
./git-release-man.phar g:b release-stable
```

#### Get latest test release version

```bash
./git-release-man.phar g:b latest-release-candidate
```

#### Get latest stable release tag

```bash
./git-release-man.phar g:b latest-release-stable
```

### Development commands

```bash
# generate secure token for travis, is required to deploy release
travis encrypt api_key_here
# Add api key variable to env
travis env set GITHUBKEY api_key_here --private -r vdubyna/git-release-man
```

