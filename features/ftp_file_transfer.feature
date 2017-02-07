Feature: A class user should be able to utilize FTP connection
         for file transfers


Scenario: A user uploads a file via FTP connection

    Given a user has valid user credentials on a remote server
    When the user connects to the server via FTP
    And initiates the file upload
    Then a matching remote file must be created

