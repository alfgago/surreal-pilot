/**
 * Performance Audit Script
 * Runs automated performance checks and generates reports
 */

const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

class PerformanceAuditor {
    constructor() {
        this.results = {
            timestamp: new Date().toISOString(),
            pages: {},
            summary: {
                totalPages: 0,
                passedPages: 0,
                failedPages: 0,
                averageLoadTime: 0,
                issues: []
            }
        };
    }

    async auditPage(url, pageName) {
        const browser = await puppeteer.launch({
            headless: true,
            args: ['--no-sandbox', '--disable-setuid-sandbox']
        });

        try {
            const page = await browser.newPage();
            
            // Enable performance monitoring
            await page.setCacheEnabled(false);
            
            // Start performance measurement
            const startTime = Date.now();
            
            // Navigate to page
            const response = await page.goto(url, {
                waitUntil: 'networkidle0',
                timeout: 30000
            });

            const loadTime = Date.now() - startTime;

            // Get performance metrics
            const metrics = await page.metrics();
            
            // Get resource loading information
            const performanceEntries = await page.evaluate(() => {
                return JSON.parse(JSON.stringify(performance.getEntriesByType('resource')));
            });

            // Get memory usage if available
            const memoryUsage = await page.evaluate(() => {
                return performance.memory ? {
                    usedJSHeapSize: performance.memory.usedJSHeapSize,
                    totalJSHeapSize: performance.memory.totalJSHeapSize,
                    jsHeapSizeLimit: performance.memory.jsHeapSizeLimit
                } : null;
            });

            // Analyze results
            const pageResult = {
                url,
                loadTime,
                status: response.status(),
                metrics,
                memoryUsage,
                resources: this.analyzeResources(performanceEntries),
                issues: this.identifyIssues(loadTime, performanceEntries, metrics),
                passed: loadTime < 2000 && response.status() === 200
            };

            this.results.pages[pageName] = pageResult;
            this.results.summary.totalPages++;
            
            if (pageResult.passed) {
                this.results.summary.passedPages++;
            } else {
                this.results.summary.failedPages++;
                this.results.summary.issues.push(...pageResult.issues);
            }

            console.log(`✓ Audited ${pageName}: ${loadTime}ms (${pageResult.passed ? 'PASS' : 'FAIL'})`);

        } catch (error) {
            console.error(`✗ Failed to audit ${pageName}:`, error.message);
            this.results.pages[pageName] = {
                url,
                error: error.message,
                passed: false
            };
            this.results.summary.totalPages++;
            this.results.summary.failedPages++;
        } finally {
            await browser.close();
        }
    }

    analyzeResources(performanceEntries) {
        const resources = {
            total: performanceEntries.length,
            byType: {},
            slowResources: [],
            largeResources: []
        };

        performanceEntries.forEach(entry => {
            const type = this.getResourceType(entry.name);
            resources.byType[type] = (resources.byType[type] || 0) + 1;

            // Flag slow resources (>1s)
            if (entry.duration > 1000) {
                resources.slowResources.push({
                    name: entry.name,
                    duration: entry.duration,
                    size: entry.transferSize
                });
            }

            // Flag large resources (>1MB)
            if (entry.transferSize > 1024 * 1024) {
                resources.largeResources.push({
                    name: entry.name,
                    duration: entry.duration,
                    size: entry.transferSize
                });
            }
        });

        return resources;
    }

    getResourceType(url) {
        if (url.includes('.css')) return 'css';
        if (url.includes('.js')) return 'javascript';
        if (url.match(/\.(jpg|jpeg|png|gif|webp|svg)$/i)) return 'image';
        if (url.match(/\.(woff|woff2|ttf|eot)$/i)) return 'font';
        if (url.includes('/api/')) return 'api';
        return 'other';
    }

    identifyIssues(loadTime, performanceEntries, metrics) {
        const issues = [];

        // Check load time
        if (loadTime > 2000) {
            issues.push({
                type: 'performance',
                severity: 'high',
                message: `Page load time (${loadTime}ms) exceeds 2s target`,
                recommendation: 'Optimize critical resources and reduce bundle size'
            });
        }

        // Check for too many resources
        if (performanceEntries.length > 50) {
            issues.push({
                type: 'performance',
                severity: 'medium',
                message: `Too many resources loaded (${performanceEntries.length})`,
                recommendation: 'Combine and minify resources, implement lazy loading'
            });
        }

        // Check for large JavaScript heap
        if (metrics.JSHeapUsedSize > 50 * 1024 * 1024) { // 50MB
            issues.push({
                type: 'memory',
                severity: 'medium',
                message: `High JavaScript memory usage (${Math.round(metrics.JSHeapUsedSize / 1024 / 1024)}MB)`,
                recommendation: 'Optimize JavaScript code and implement code splitting'
            });
        }

        return issues;
    }

    async generateReport() {
        // Calculate summary statistics
        const loadTimes = Object.values(this.results.pages)
            .filter(page => page.loadTime)
            .map(page => page.loadTime);
        
        this.results.summary.averageLoadTime = loadTimes.length > 0 
            ? Math.round(loadTimes.reduce((a, b) => a + b, 0) / loadTimes.length)
            : 0;

        // Generate HTML report
        const htmlReport = this.generateHtmlReport();
        
        // Save reports
        const reportsDir = path.join(__dirname, '..', 'storage', 'performance-reports');
        if (!fs.existsSync(reportsDir)) {
            fs.mkdirSync(reportsDir, { recursive: true });
        }

        const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
        const jsonPath = path.join(reportsDir, `performance-${timestamp}.json`);
        const htmlPath = path.join(reportsDir, `performance-${timestamp}.html`);

        fs.writeFileSync(jsonPath, JSON.stringify(this.results, null, 2));
        fs.writeFileSync(htmlPath, htmlReport);

        console.log('\n📊 Performance Audit Complete');
        console.log(`📁 Reports saved to:`);
        console.log(`   JSON: ${jsonPath}`);
        console.log(`   HTML: ${htmlPath}`);
        console.log(`\n📈 Summary:`);
        console.log(`   Total Pages: ${this.results.summary.totalPages}`);
        console.log(`   Passed: ${this.results.summary.passedPages}`);
        console.log(`   Failed: ${this.results.summary.failedPages}`);
        console.log(`   Average Load Time: ${this.results.summary.averageLoadTime}ms`);
        
        if (this.results.summary.issues.length > 0) {
            console.log(`\n⚠️  Issues Found: ${this.results.summary.issues.length}`);
            this.results.summary.issues.forEach(issue => {
                console.log(`   ${issue.severity.toUpperCase()}: ${issue.message}`);
            });
        }

        return this.results;
    }

    generateHtmlReport() {
        return `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Audit Report</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007cba; padding-bottom: 10px; }
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
        .metric { background: #f8f9fa; padding: 20px; border-radius: 6px; text-align: center; }
        .metric-value { font-size: 2em; font-weight: bold; color: #007cba; }
        .metric-label { color: #666; margin-top: 5px; }
        .page-results { margin-top: 30px; }
        .page-result { margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 6px; }
        .page-result.passed { border-left: 4px solid #28a745; }
        .page-result.failed { border-left: 4px solid #dc3545; }
        .issues { margin-top: 20px; }
        .issue { padding: 10px; margin: 5px 0; border-radius: 4px; }
        .issue.high { background: #f8d7da; border: 1px solid #f5c6cb; }
        .issue.medium { background: #fff3cd; border: 1px solid #ffeaa7; }
        .issue.low { background: #d1ecf1; border: 1px solid #bee5eb; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Performance Audit Report</h1>
        <p><strong>Generated:</strong> ${this.results.timestamp}</p>
        
        <div class="summary">
            <div class="metric">
                <div class="metric-value">${this.results.summary.totalPages}</div>
                <div class="metric-label">Total Pages</div>
            </div>
            <div class="metric">
                <div class="metric-value">${this.results.summary.passedPages}</div>
                <div class="metric-label">Passed</div>
            </div>
            <div class="metric">
                <div class="metric-value">${this.results.summary.failedPages}</div>
                <div class="metric-label">Failed</div>
            </div>
            <div class="metric">
                <div class="metric-value">${this.results.summary.averageLoadTime}ms</div>
                <div class="metric-label">Avg Load Time</div>
            </div>
        </div>

        <div class="page-results">
            <h2>Page Results</h2>
            ${Object.entries(this.results.pages).map(([name, result]) => `
                <div class="page-result ${result.passed ? 'passed' : 'failed'}">
                    <h3>${name} ${result.passed ? '✅' : '❌'}</h3>
                    <p><strong>URL:</strong> ${result.url}</p>
                    ${result.loadTime ? `<p><strong>Load Time:</strong> ${result.loadTime}ms</p>` : ''}
                    ${result.error ? `<p><strong>Error:</strong> ${result.error}</p>` : ''}
                    
                    ${result.issues && result.issues.length > 0 ? `
                        <div class="issues">
                            <h4>Issues:</h4>
                            ${result.issues.map(issue => `
                                <div class="issue ${issue.severity}">
                                    <strong>${issue.type.toUpperCase()}:</strong> ${issue.message}
                                    <br><em>Recommendation: ${issue.recommendation}</em>
                                </div>
                            `).join('')}
                        </div>
                    ` : ''}
                </div>
            `).join('')}
        </div>
    </div>
</body>
</html>`;
    }
}

// Main execution
async function runPerformanceAudit() {
    const auditor = new PerformanceAuditor();
    
    // Define pages to audit
    const baseUrl = process.env.APP_URL || 'http://surreal-pilot.local';
    const pages = [
        { name: 'Landing Page', url: `${baseUrl}/` },
        { name: 'Login', url: `${baseUrl}/login` },
        { name: 'Register', url: `${baseUrl}/register` },
        { name: 'Dashboard', url: `${baseUrl}/dashboard` },
        { name: 'Chat', url: `${baseUrl}/chat` },
        { name: 'Games', url: `${baseUrl}/games` },
        { name: 'Templates', url: `${baseUrl}/templates` },
        { name: 'Settings', url: `${baseUrl}/settings` }
    ];

    console.log('🚀 Starting Performance Audit...\n');

    // Audit each page
    for (const page of pages) {
        await auditor.auditPage(page.url, page.name);
    }

    // Generate final report
    await auditor.generateReport();
}

// Run if called directly
if (require.main === module) {
    runPerformanceAudit().catch(console.error);
}

module.exports = { PerformanceAuditor, runPerformanceAudit };