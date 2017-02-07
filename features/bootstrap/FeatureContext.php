<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeFeatureScope;
use Behat\Behat\Hook\Scope\AfterFeatureScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Behat\Tester\Exception\PendingException;

require_once(__DIR__.'/../../lib/file_transfer_lib.php');

use FileTransfer as FT;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context
{
    const TEST_FILENAME = 'dump.tar.gz';
    const TEST_FILE_LOCATION = '/var/www';

    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct()
    {
    }

    /** @BeforeScenario */
    public function before(BeforeScenarioScope $scope)
    {
        $this->testData = [
            'call_data' => []
        ];
    }

    /**
     * @Given a user has valid user credentials on a remote server
     */
    public function aUserHasValidUserCredentialsOnARemoteServer()
    {
        $this->testData['user_credentials'] = [
            'username' => 'dennis',
            'password' => '9!m8!yKG)N5sf\lk+CfH+b2N',
            'host' => '192.168.56.12'
        ];
    }

    /**
     * @When the user connects to the server via SSH
     */
    public function theUserConnectsToTheServerViaSsh()
    {
        $factory = new FT\Factory();
        $user_data = $this->testData['user_credentials'];
        
        $this->testData['session'] = $factory->getConnection('ssh',
            $user_data['username'],
            $user_data['password'],
            $user_data['host']
        );
    }

    /**
     * @When initiates the file download
     */
    public function initiatesTheFileDownload()
    {
        $this->testData['session']->cd(self::TEST_FILE_LOCATION)
            ->download(self::TEST_FILENAME)
            ->close();
    }

    /**
     * @Then a matching local file must be created
     */
    public function aMatchingLocalFileMustBeCreated()
    {
        PHPUnit_Framework_Assert::assertFileExists(
            self::TEST_FILENAME,
            'The requested file is downloaded'
        );
    }

    /**
     * @When the user connects to the server via FTP
     */
    public function theUserConnectsToTheServerViaFtp()
    {
        $factory = new FT\Factory();
        $user_data = $this->testData['user_credentials'];
        
        $this->testData['session'] = $factory->getConnection('ftp',
            $user_data['username'],
            $user_data['password'],
            $user_data['host']
        );
    }

    /**
     * @When initiates the file upload
     */
    public function initiatesTheFileUpload()
    {
        // Upload README.md to the remote server
        $this->testData['session']->upload('README.md');
    }

    /**
     * @Then a matching remote file must be created
     */
    public function aMatchingRemoteFileMustBeCreated()
    {
        // Now let's make sure we do have it in the list
        $result = $this->testData['session']->exec('ls -1');
        PHPUnit_Framework_Assert::assertTrue(
            in_array('README.md', $result),
            'Got the uploaded file'
        );
    }

}
