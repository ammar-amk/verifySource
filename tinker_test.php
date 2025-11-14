<?php
// Test script for credibility system

use App\Services\DomainTrustService;

echo "Testing DomainTrustService...\n";
$domainService = new DomainTrustService();
$result = $domainService->quickDomainAnalysis('reuters.com');
echo "Domain: reuters.com\n";
echo "Trust Score: " . $result->trust_score . "\n";
echo "Classification: " . $result->credibility_classification . "\n";

echo "\nTesting with BBC...\n";
$result2 = $domainService->quickDomainAnalysis('bbc.com');
echo "Domain: bbc.com\n";  
echo "Trust Score: " . $result2->trust_score . "\n";
echo "Classification: " . $result2->credibility_classification . "\n";