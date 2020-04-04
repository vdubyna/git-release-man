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
        system('ls -la');
        if (!is_dir('test')) {
            echo "Make test dir. " . PHP_EOL;
            mkdir('test');
        } else {
            echo "Test dir existsn. Not create" . PHP_EOL;
        }
        system('pwd');
        system('ls -la');
        chdir('test');
    }

    /**
     * @AfterScenario
     */
    public function moveOutOfTestDir()
    {
        echo 'before move out ' . getcwd() . PHP_EOL;
        chdir(realpath('..'));
        if (is_dir('test')) {
            echo "Delete test dir." . PHP_EOL;
            system('rm -rf ' . realpath('test'));
        } else {
            echo "No need delete test dir." . PHP_EOL;

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
     * @When /^I run g\-man command "([^"]*)" with action "([^"]*)" and options "([^"]*)"$/
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

    /**
     * @Then /^I should see error "([^"]*)"$/
     */
    public function iShouldSeeError($error)
    {
        assertContains($error, implode('', $this->output));
    }

    /**
     * @Given /^Release candidate should not be created$/
     */
    public function releaseCandidateShouldNotBeCreated()
    {
        exec('git branch', $output);
        print_r($output);
        assertNotContains('-RC1', implode('', $output));
    }

    /**
     * @When /^I run g\-man failed command "([^"]*)" with action "([^"]*)" and options "([^"]*)"$/
     */
    public function iRunGManFailedCommandWithActionAndOptions($command, $action, $options)
    {
        exec("php -f ../bin/git-release-man {$command} {$action} {$options}", $output);
        print_r($output);
        $this->output = $output;
    }

    /**
     * @Given /^I do updates "([^"]*)" in file "([^"]*)" in branch "([^"]*)" and commit them$/
     */
    public function iDoUpdatesInBranchAndCommitThem($update, $file, $branch)
    {
        exec("git checkout {$branch} && echo '{$update}' >> $file ' .
            '&& git add {$file} && git commit -m'update'", $output);
        $this->output = $output;
    }
}
