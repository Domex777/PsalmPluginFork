Feature: Request types
  \Illuminate\Http\Request has type support

  Background:
    Given I have the following config
      """
      <?xml version="1.0"?>
      <psalm errorLevel="2" reportMixedIssues="false">
        <projectFiles>
          <directory name="."/>
          <ignoreFiles> <directory name="../../vendor"/> </ignoreFiles>
        </projectFiles>
        <plugins>
          <pluginClass class="Psalm\LaravelPlugin\Plugin"/>
        </plugins>
      </psalm>
      """
    And I have the following code preamble
    """
    <?php declare(strict_types=1);

     namespace Tests\Psalm\LaravelPlugin\Sandbox;

     use \Illuminate\Http\Request;
    """

  Scenario: input returns various types
    Given I have the following code
    """
    function test(Request $request): bool
    {
      return $request->input('foo', false);
    }
    """
    When I run Psalm
    Then I see no errors
