Feature: FeatureCommand
  To manage feature command
  As a GitReleaseMan user
  I use git-release-man tool

  Background:
    Given I have initiated git repository
    And I have master branch with committed readme file

  @featureStart
  Scenario: Start new feature
    Given I am in initiated git repository
    When I run git-release-man command "git-release:feature" with action "start" and options "--name feature/test-1 --force=true"
    Then I should see new feature started
    # todo verify tags
    # todo verify current branch
    # todo verify exceptions

  @featureClose
  Scenario: Close feature
    Given I am in initiated git repository
    And I run git-release-man command "git-release:feature" with action "start" and options "--name feature/test-1 --force=true"
    When I run git-release-man command "git-release:feature" with action "close" and options "--name feature/test-1 --force=true"
    Then I should see new feature closed

  @featureInfo
  Scenario: Feature info
    Given I am in initiated git repository
    And I run git-release-man command "git-release:feature" with action "start" and options "--name feature/test-1 --force=true"
    When I run git-release-man command "git-release:feature" with action "info" and options "--name feature/test-1"
    Then I should see feature info
    # todo verify tags
    # todo verify current branch
    # todo verify exceptions

  @featureReleaseCandidate
  Scenario: Feature release candidate
    Given I am in initiated git repository
    And I run git-release-man command "git-release:feature" with action "start" and options "--name feature/test-1 --force=true"
    When I run git-release-man command "git-release:feature" with action "release-candidate" and options "--name feature/test-1 --force=true"
    Then I run git-release-man command "git-release:feature" with action "info" and options "--name feature/test-1"
    And I should see feature is marked as release candidate
    # todo verify tags
    # todo verify current branch
    # todo verify exceptions

  @featureReleaseStable
  Scenario: Feature release stable
    Given I am in initiated git repository
    And I run git-release-man command "git-release:feature" with action "start" and options "--name feature/test-1 --force=true"
    And I run git-release-man command "git-release:feature" with action "release-candidate" and options "--name feature/test-1 --force=true"
    When I run git-release-man command "git-release:feature" with action "release-stable" and options "--name feature/test-1 --force=true"
    Then I run git-release-man command "git-release:feature" with action "info" and options "--name feature/test-1"
    And I should see feature is marked as release stable
    # todo verify tags
    # todo verify current branch
    # todo verify exceptions

  @featureReset
  Scenario: Feature release reset
    Given I am in initiated git repository
    And I run git-release-man command "git-release:feature" with action "start" and options "--name feature/test-1 --force=true"
    And I run git-release-man command "git-release:feature" with action "release-candidate" and options "--name feature/test-1 --force=true"
    And I run git-release-man command "git-release:feature" with action "release-stable" and options "--name feature/test-1 --force=true"
    When I run git-release-man command "git-release:feature" with action "reset" and options "--name feature/test-1 --force=true"
    Then I run git-release-man command "git-release:feature" with action "info" and options "--name feature/test-1 --force=true"
    And I should see feature is marked as started
    # todo verify tags
    # todo verify current branch
    # todo verify exceptions

  @buildFeaturesList
  Scenario: Get features list with short info
    Given I am in initiated git repository
    And I run git-release-man command "git-release:feature" with action "start" and options "--name feature/test-1 --force=true"
    And I run git-release-man command "git-release:feature" with action "release-candidate" and options "--name feature/test-1 --force=true"
    And I run git-release-man command "git-release:feature" with action "start" and options "--name feature/test-2 --force=true"
    And I run git-release-man command "git-release:feature" with action "release-candidate" and options "--name feature/test-2 --force=true"
    When I run git-release-man command "git-release:build" with action "features-list" and options ""
    Then I should see features list
    # todo verify tags
    # todo verify current branch
    # todo verify exceptions

  @buildLatestReleaseCandidate
  Scenario: Get latest release-candidate version
    Given I am in initiated git repository
    And I run git-release-man command "git-release:feature" with action "start" and options "--name feature/test-1 --force=true"
    And I run git-release-man command "git-release:feature" with action "release-candidate" and options "--name feature/test-1 --force=true"
    And I run git-release-man command "git-release:feature" with action "start" and options "--name feature/test-2 --force=true"
    And I run git-release-man command "git-release:feature" with action "release-candidate" and options "--name feature/test-2 --force=true"
    And I run git-release-man command "git-release:build" with action "release-candidate" and options "--force=true"
    When I run git-release-man command "git-release:build" with action "latest-release-candidate" and options ""
    Then I should see latest release candidate version
    # todo verify tags
    # todo verify current branch
    # todo verify exceptions

  @buildLatestReleaseStable
  Scenario: Get latest release-stable version
    Given I am in initiated git repository
    When I run git-release-man command "git-release:build" with action "latest-release-stable" and options ""
    Then I should see latest release stable version "1.0.0"
    # todo verify tags
    # todo verify current branch
    # todo verify exceptions

  @buildLatestReleaseStableV1
  Scenario: Get latest release-stable version
    Given I am in initiated git repository
    And I run git-release-man command "git-release:feature" with action "start" and options "--name feature/test-1 --force=true"
    And I run git-release-man command "git-release:feature" with action "release-candidate" and options "--name feature/test-1 --force=true"
    And I run git-release-man command "git-release:feature" with action "release-stable" and options "--name feature/test-1 --force=true"
    And I run git-release-man command "git-release:feature" with action "start" and options "--name feature/test-2 --force=true"
    And I run git-release-man command "git-release:feature" with action "release-candidate" and options "--name feature/test-2 --force=true"
    And I run git-release-man command "git-release:feature" with action "release-stable" and options "--name feature/test-2 --force=true"
    And I run git-release-man command "git-release:build" with action "release-candidate" and options "--force=true"
    And I run git-release-man command "git-release:build" with action "release-stable" and options "--force=true"
    When I run git-release-man command "git-release:build" with action "latest-release-stable" and options ""
    Then I should see latest release stable version "1.0.1"
    # todo verify tags
    # todo verify current branch
    # todo verify exceptions

  @buildLatestReleaseStableV2
  Scenario: Get latest release-stable version
    Given I am in initiated git repository
    And I run git-release-man command "git-release:feature" with action "start" and options "--name feature/test-1 --force=true"
    And I run git-release-man command "git-release:feature" with action "release-candidate" and options "--name feature/test-1 --force=true"
    And I run git-release-man command "git-release:feature" with action "release-stable" and options "--name feature/test-1 --force=true"
    And I run git-release-man command "git-release:build" with action "release-candidate" and options "--force=true"
    And I run git-release-man command "git-release:build" with action "release-stable" and options "--force=true"
    And I should see latest release stable version "1.0.1"
    And I run git-release-man command "git-release:feature" with action "start" and options "--name feature/test-2 --force=true"
    And I run git-release-man command "git-release:feature" with action "release-candidate" and options "--name feature/test-2 --force=true"
    And I run git-release-man command "git-release:feature" with action "release-stable" and options "--name feature/test-2 --force=true"
    And I run git-release-man command "git-release:build" with action "release-candidate" and options "--force=true"
    When I run git-release-man command "git-release:build" with action "release-stable" and options "--force=true"
    Then I should see latest release stable version "1.0.2"
