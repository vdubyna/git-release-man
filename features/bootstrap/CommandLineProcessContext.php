<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Tester\Exception\PendingException;

require_once __DIR__.'/../../vendor/phpunit/phpunit/src/Framework/Assert/Functions.php';

class CommandLineProcessContext implements Context
{
    private $output;

    /**
     * @BeforeScenario
     */
    public function moveIntoTestDir()
    {
        if (!is_dir('test')) {
            mkdir('test');
        }
        chdir('test');
    }

    /**
     * @AfterScenario
     */
    public function moveOutOfTestDir()
    {
        chdir(realpath('..'));
        if (is_dir('test')) {
            system('rm -rf '.realpath('test'));
        }
    }

    /**
     * @Given /^I have initiated git repository$/
     */
    public function iHaveInitiatedGitRepository()
    {
        exec('git init', $output);
        print_r($output);
    }

    /**
     * @Given /^I have master branch with committed readme file$/
     */
    public function iHaveMasterBranchWithCommittedReadmeFile()
    {
        file_put_contents('README.md', 'This is test');
        exec('git add . && git commit -m"Init commit"', $output);
        print_r($output);
    }

    /**
     * @Given /^I am in initiated git repository$/
     * @throws Exception
     */
    public function iAmInInitiatedGitRepository()
    {
        exec('git branch', $output);
        assertContains('master', implode('', $output));
    }

    /**
     * @Then /^I should see new feature started$/
     */
    public function iShouldSeeNewFeatureStarted()
    {
        exec('git branch', $output);
        print_r($output);
        assertContains('feature/test-1', implode('', $output));
    }

    /**
     * @When /^I run git\-release\-man command "([^"]*)" with action "([^"]*)" and options "([^"]*)"$/
     */
    public function iRunGitReleaseManCommandFeatureWithOptions($command, $action, $options)
    {
        exec("php -f ../bin/git-release-man {$command} {$action} {$options}", $output);
        print_r($output);
        assertContains('OK', implode('', $output));
        $this->output = $output;
    }

    /**
     * @Then /^I should see new feature closed$/
     */
    public function iShouldSeeNewFeatureClosed()
    {
        //Then I should see new feature closed
        exec('git branch', $output);
        print_r($output);
        assertNotContains('feature/test-1', implode('', $output));
    }

    /**
     * @Then /^I should see feature info$/
     */
    public function iShouldSeeFeatureInfo()
    {
        assertContains('feature/test-1', implode('', $this->output));
    }

    /**
     * @Then /^I should see feature is marked as release candidate$/
     */
    public function iShouldSeeFeatureIsMarkedAsReleaseCandidate()
    {
        assertContains('STATUS: release-candidate', implode('', $this->output));
    }

    /**
     * @Given /^I should see feature is marked as release stable$/
     */
    public function iShouldSeeFeatureIsMarkedAsReleaseStable()
    {
        assertContains('STATUS: release-stable', implode('', $this->output));
    }

    /**
     * @Given /^I should see feature is marked as started$/
     */
    public function iShouldSeeFeatureIsMarkedAsStarted()
    {
        assertContains('STATUS: started', implode('', $this->output));
    }

    /**
     * @Then /^I should see features list$/
     */
    public function iShouldSeeFeaturesList()
    {
        assertContains('feature/test-1', implode('', $this->output));
        assertContains('feature/test-2', implode('', $this->output));
    }

    /**
     * @Then /^I should see latest release candidate version$/
     */
    public function iShouldSeeLatestReleaseCandidateVersion()
    {
        assertContains('v1.0.1-RC1', implode('', $this->output));
    }

    /**
     * @Then /^I should see latest release stable version "([^"]*)"$/
     */
    public function iShouldSeeLatestReleaseStableVersion($version)
    {
        assertContains($version, implode('', $this->output));
    }
}
