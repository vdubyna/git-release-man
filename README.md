# Git release manager

It is console tool which allows to create release and pre-release tags on github.
The workflow looks as follows:

* **Init configuration.** It can be the folder of the project or any other folder.
 The tool does not depend on the repository. It requires api token.
* **Open feature.** It creates the branch with prefix `feature-` directly on github.
 If option name is not set we try to check and if current folder is repository and the branch name starts
 with `feature-` we use it as the name option. It is useful for quick test/release current feature.
* **Test feature.** This command opens pull request to master branch, if it is not opened yet
 and marks the Pull request with label `IN-BETA`. It is used to compile test release (Release Candidate)
* **Release feature.** This command marks pull request with `OK-PROD` label.
 It is used to compile stable release.
* **Create Test release(Release candidate).** This command creates `Release Candidate` branch
 and merge pull requests marked `IN-BETA` into it. It also creates `pre-release` tag. So, you can push this
 tag into test server.
* **Create Test release(Release candidate).** This command verifies if Pull Requests marked `OK-PROD` can
 be merged and merge them with `squash` stratagy into master. It also creates `release` tag.
 So, you can push this tag into production server.

### Install

Go to latest release and download `git-release-man.phar`
https://github.com/vdubyna/git-release-man/releases/latest

### Usage

#### Init configuration

```bash
./git-release-man.phar git-release:build init
```


#### Open new Feature

```bash
./git-release-man.phar git-release:feature open --name FEATURE_NAME_HERE
```

#### Close Feature

Removes feature branch from remote repository

```bash
./git-release-man.phar git-release:feature close --name FEATURE_NAME_HERE
```

#### Reopen Feature

Removes labels from pull request. It exclude feature from builds.

```bash
./git-release-man.phar git-release:feature reopen --name FEATURE_NAME_HERE
```

#### List available features

```bash
./git-release-man.phar git-release:feature list
```

#### Mark Feature ready for testing

```bash
./git-release-man.phar git-release:feature test --name FEATURE_NAME_HERE
```

#### Mark Feature ready for release

```bash
./git-release-man.phar git-release:feature release --name FEATURE_NAME_HERE
```

#### Create test release (Release Candidate) Tag and Branch

```bash
./git-release-man.phar git-release:build test
```

#### Merge pull requests into master branch, create release tag

```bash
./git-release-man.phar git-release:build release
```

#### Get latest release tag

```bash
./git-release-man.phar git-release:build latest-release
```

#### Get latest test release tag

```bash
./git-release-man.phar git-release:build latest-test-release
```


### Development commands

```bash
# generate secure token for travis, is required to deploy release
travis encrypt api_key_here
# Add api key variable to env
travis env set GITHUBKEY api_key_here --private -r vdubyna/git-release-man
```