<?php namespace BambooHR\Guardrail\Tests\Filters;

use BambooHR\Guardrail\Filters\UnifiedDiffFilter;
use BambooHR\Guardrail\Tests\TestSuiteSetup;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the UnifiedDiffFilter class
 * 
 * @package BambooHR\Guardrail\Tests\Filters
 */
class TestUnifiedDiffFilter extends TestCase {

    /**
     * Test parsing a simple patch file with one file and one chunk
     * 
     * @return void
     */
    public function testParseSimplePatch() {
        $patchContent = [
            "--- a/src/Example.php",
            "+++ b/src/Example.php",
            "@@ -10,6 +10,7 @@ class Example {",
        ];
        
        $result = UnifiedDiffFilter::parse($patchContent);
        
        $this->assertArrayHasKey('src/Example.php', $result);
        $this->assertEquals([[10, 16]], $result['Example.php']);
    }
    
    /**
     * Test parsing a patch file with multiple files
     * 
     * @return void
     */
    public function testParseMultipleFiles() {
        $patchContent = [
            "--- a/src/Example1.php",
            "+++ b/src/Example1.php",
            "@@ -5,6 +5,7 @@ class Example1 {",
            "--- a/src/Example2.php",
            "+++ b/src/Example2.php",
            "@@ -15,6 +15,7 @@ class Example2 {",
        ];
        
        $result = UnifiedDiffFilter::parse($patchContent);
        
        $this->assertArrayHasKey('src/Example1.php', $result);
        $this->assertArrayHasKey('src/Example2.php', $result);
        $this->assertEquals([[5, 11]], $result['Example1.php']);
        $this->assertEquals([[15, 21]], $result['Example2.php']);
    }
    
    /**
     * Test parsing a patch file with multiple chunks in one file
     * 
     * @return void
     */
    public function testParseMultipleChunks() {
        $patchContent = [
            "--- a/src/Example.php",
            "+++ b/src/Example.php",
            "@@ -5,6 +5,7 @@ class Example {",
            "@@ -20,6 +20,7 @@ class Example {",
        ];
        
        $result = UnifiedDiffFilter::parse($patchContent);
        
        $this->assertArrayHasKey('src/Example.php', $result);
        $this->assertEquals([[5, 11], [20, 26]], $result['src/Example.php']);
    }
    
    /**
     * Test parsing a patch file with single-line hunks
     * 
     * @return void
     */
    public function testParseSingleLineHunks() {
        $patchContent = [
            "--- a/src/Example.php",
            "+++ b/src/Example.php",
            "@@ -5 +5 @@ class Example {",
        ];
        
        $result = UnifiedDiffFilter::parse($patchContent);
        
        $this->assertArrayHasKey('Example.php', $result);
        $this->assertEquals([[5, 5]], $result['Example.php']);
    }
    
    /**
     * Test the ignoreParts parameter functionality
     * 
     * @return void
     */
    public function testIgnoreParts() {
        $patchContent = [
            "--- a/src/Folder/Example.php",
            "+++ b/src/Folder/Example.php",
            "@@ -5,6 +5,7 @@ class Example {",
        ];
        
        // Default ignoreParts = 1
        $result1 = UnifiedDiffFilter::parse($patchContent);
        $this->assertArrayHasKey('src/Folder/Example.php', $result1);
        
        // ignoreParts = 2
        $result2 = UnifiedDiffFilter::parse($patchContent, 2);
        $this->assertArrayHasKey('src/Example.php', $result2);
    }
    
    /**
     * Test binary search for a line number within a range
     * 
     * @return void
     */
    public function testBinarySearchInRange() {
        $filter = [
            'Example.php' => [[10, 20], [30, 40]]
        ];
        
        $diffFilter = new UnifiedDiffFilter($filter);
        
        // Line within first range
        $this->assertTrue($diffFilter->binary_search('Example.php', 15));
        
        // Line within second range
        $this->assertTrue($diffFilter->binary_search('Example.php', 35));
        
        // Line outside ranges
        $this->assertFalse($diffFilter->binary_search('Example.php', 25));
    }
    
    /**
     * Test binary search for an exact line number match
     * 
     * @return void
     */
    public function testBinarySearchExactMatch() {
        $filter = [
            'Example.php' => [[5, 5], [10, 10], [15, 15]]
        ];
        
        $diffFilter = new UnifiedDiffFilter($filter);
        
        // Exact matches
        $this->assertTrue($diffFilter->binary_search('Example.php', 5));
        $this->assertTrue($diffFilter->binary_search('Example.php', 10));
        $this->assertTrue($diffFilter->binary_search('Example.php', 15));
        
        // Non-matches
        $this->assertFalse($diffFilter->binary_search('Example.php', 7));
        $this->assertFalse($diffFilter->binary_search('Example.php', 20));
    }
    
    /**
     * Test binary search with mixed arrays containing both single line numbers and ranges
     * 
     * @return void
     */
    public function testBinarySearchMixedArray() {
        $filter = [
            'Example.php' => [[5, 5], [10, 15], [20, 20], [25, 30]]
        ];
        
        $diffFilter = new UnifiedDiffFilter($filter);
        
        // Single line matches
        $this->assertTrue($diffFilter->binary_search('Example.php', 5));
        $this->assertTrue($diffFilter->binary_search('Example.php', 20));
        
        // Range matches
        $this->assertTrue($diffFilter->binary_search('Example.php', 10));
        $this->assertTrue($diffFilter->binary_search('Example.php', 12));
        $this->assertTrue($diffFilter->binary_search('Example.php', 15));
        $this->assertTrue($diffFilter->binary_search('Example.php', 25));
        $this->assertTrue($diffFilter->binary_search('Example.php', 28));
        $this->assertTrue($diffFilter->binary_search('Example.php', 30));
        
        // Non-matches
        $this->assertFalse($diffFilter->binary_search('Example.php', 7));
        $this->assertFalse($diffFilter->binary_search('Example.php', 18));
        $this->assertFalse($diffFilter->binary_search('Example.php', 22));
    }
    
    /**
     * Test binary search edge cases
     * 
     * @return void
     */
    public function testBinarySearchEdgeCases() {
        // Empty filter
        $emptyFilter = new UnifiedDiffFilter([]);
        $this->assertFalse($emptyFilter->binary_search('Example.php', 10));
        
        // Non-existent file
        $filter = new UnifiedDiffFilter(['OtherFile.php' => [[10, 10]]]);
        $this->assertFalse($filter->binary_search('Example.php', 10));
        
        // Empty line array for file
        $emptyLineFilter = new UnifiedDiffFilter(['Example.php' => []]);
        $this->assertFalse($emptyLineFilter->binary_search('Example.php', 10));
    }
    
    /**
     * Test integration between parse() and binary_search via shouldEmit()
     * 
     * @return void
     */
    public function testIntegrationParseBinarySearch() {
        $patchContent = [
            "--- a/src/Example.php",
            "+++ b/src/Example.php",
            "@@ -10,6 +10,7 @@ class Example {",
            "@@ -20 +20 @@ class Example {",
        ];
        
        $filter = UnifiedDiffFilter::parse($patchContent);
        $diffFilter = new UnifiedDiffFilter($filter);
        
        // Lines that should emit
        $this->assertTrue($diffFilter->shouldEmit('src/Example.php', 'ERROR_TYPE', 10));
        $this->assertTrue($diffFilter->shouldEmit('src/Example.php', 'ERROR_TYPE', 15));
        $this->assertTrue($diffFilter->shouldEmit('src/Example.php', 'ERROR_TYPE', 20));
        
        // Lines that should not emit
        $this->assertFalse($diffFilter->shouldEmit('src/Example.php', 'ERROR_TYPE', 5));
        $this->assertFalse($diffFilter->shouldEmit('src/Example.php', 'ERROR_TYPE', 18));
        $this->assertFalse($diffFilter->shouldEmit('src/Example.php', 'ERROR_TYPE', 25));
        $this->assertFalse($diffFilter->shouldEmit('src/OtherFile.php', 'ERROR_TYPE', 10));
    }
}
