Feature: A class user should be able to utilize SSH connection
         for file transfers


Scenario: A user downloads a file via SSH connection

    Given a user has valid user credentials on a remote server
    When the user connects to the server via SSH
    And initiates the file download
    Then a matching local file must be created

