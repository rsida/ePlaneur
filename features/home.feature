Feature: Home page
    In order to discover ePlaneur
    As a visitor
    I want to reach the home page

    Scenario: The home page is available
        When I go to "/"
        Then the response status code should be 200
        And I should see "ePlaneur"
