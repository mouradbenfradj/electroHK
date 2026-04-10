# Script to fix the cloned website - update URLs and download all assets
$baseUrl = "https://azyena.tn"
$outputDir = "c:\Mourad\www\electro\electro236\templates\frontOffice\electrohk"
$cdnBase = "https://azyena.tn/cdn"

Write-Host "Starting website fix..."

# Read the index.html
$indexPath = Join-Path $outputDir "index.html"
$content = Get-Content $indexPath -Raw -ErrorAction SilentlyContinue

if ($content) {
    Write-Host "Processing index.html..."
    
    # 1. Fix CSS URLs
    $cssPattern = 'href="(//azyena\.tn/cdn/shop/t/\d+/assets/[^"]+\.css[^"]*)"'
    $content = $content -replace $cssPattern, 'href="assets/css/$1"'
    
    # More specific patterns for Shopify CDN
    $content = $content -replace 'href="//azyena\.tn/cdn/shop/files/([^"]+)"', 'href="assets/images/$1"'
    $content = $content -replace 'href="//azyena\.tn/cdn/shop/t/(\d+)/assets/([^"]+)"', 'href="assets/css/$2"'
    
    # 2. Fix JS URLs
    $content = $content -replace 'src="//azyena\.tn/cdn/shop/t/(\d+)/assets/([^"]+)"', 'src="assets/js/$2"'
    $content = $content -replace 'src="//azyena\.tn/cdn/shopifycloud/([^"]+)"', 'src="assets/js/$1"'
    $content = $content -replace 'src="https://cdn\.shopify\.com/([^"]+)"', 'src="assets/js/$1"'
    
    # 3. Fix Image URLs
    $content = $content -replace 'src="//azyena\.tn/cdn/shop/files/([^?]+)(\?[^"]*)?"', 'src="assets/images/$1"'
    $content = $content -replace 'src="https://azyena\.tn/cdn/shop/files/([^?]+)(\?[^"]*)?"', 'src="assets/images/$1"'
    $content = $content -replace 'srcset="//azyena\.tn/cdn/shop/files/([^?]+)(\?[^"]*)?"', 'srcset="assets/images/$1"'
    
    # 4. Fix font URLs
    $content = $content -replace 'href="//fonts\.shopifycdn\.com/([^"]+)"', 'href="assets/fonts/$1"'
    $content = $content -replace 'href="//azyena\.tn/cdn/fonts/([^"]+)"', 'href="assets/fonts/$1"'
    
    # Save fixed content
    $content | Out-File -FilePath $indexPath -Encoding UTF8
    Write-Host "Fixed index.html"
}

Write-Host "Now downloading additional assets from the website..."

# Create directories
$cssDir = Join-Path $outputDir "assets\css"
$jsDir = Join-Path $outputDir "assets\js"
$fontsDir = Join-Path $outputDir "assets\fonts"

if (!(Test-Path $cssDir)) { New-Item -ItemType Directory -Path $cssDir -Force }
if (!(Test-Path $jsDir)) { New-Item -ItemType Directory -Path $jsDir -Force }
if (!(Test-Path $fontsDir)) { New-Item -ItemType Directory -Path $fontsDir -Force }

# Function to download file
function Download-Asset {
    param($url, $path)
    try {
        if (!(Test-Path $path)) {
            $webClient = New-Object System.Net.WebClient
            $webClient.DownloadFile($url, $path)
            $webClient.Dispose()
            Write-Host "Downloaded: $([System.IO.Path]::GetFileName($path))"
        }
    } catch {
        Write-Host "Failed: $url"
    }
}

# Download main CSS files
$cssFiles = @(
    "https://azyena.tn/cdn/shop/t/5/assets/vendor.css",
    "https://azyena.tn/cdn/shop/t/5/assets/theme.css",
    "https://azyena.tn/cdn/shop/t/5/assets/compare.css",
    "https://azyena.tn/cdn/shop/t/5/assets/component-custom-card.css"
)

foreach ($css in $cssFiles) {
    $fileName = $css -replace 'https://azyena\.tn/cdn/shop/t/\d+/assets/', ''
    $fileName = $fileName -replace '\?.*', ''
    $path = Join-Path $cssDir $fileName
    Download-Asset $css $path
}

# Download main JS files
$jsFiles = @(
    "https://azyena.tn/cdn/shop/t/5/assets/vendor.js",
    "https://azyena.tn/cdn/shop/t/5/assets/theme.js",
    "https://azyena.tn/cdn/shop/t/5/assets/header.js",
    "https://azyena.tn/cdn/shop/t/5/assets/search.js"
)

foreach ($js in $jsFiles) {
    $fileName = $js -replace 'https://azyena\.tn/cdn/shop/t/\d+/assets/', ''
    $path = Join-Path $jsDir $fileName
    Download-Asset $js $path
}

# Download images from the website
$imgUrls = @(
    "https://azyena.tn/cdn/shop/files/LOGO-AZYENA-DORE-H.png",
    "https://azyena.tn/cdn/shop/files/favicon-2.png",
    "https://azyena.tn/cdn/shop/files/ICON-DORE-LOGO.png",
    "https://azyena.tn/cdn/shop/files/small-business-interior.jpg"
)

foreach ($img in $imgUrls) {
    $fileName = $img -replace 'https://azyena\.tn/cdn/shop/files/', ''
    $path = Join-Path $outputDir "assets\images\$fileName"
    Download-Asset $img $path
}

Write-Host "Fix completed!"

