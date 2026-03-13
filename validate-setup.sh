#!/bin/bash

# 🧪 Script para validar configuración de N8N + Gemini
# Uso: bash validate-setup.sh

echo "=========================================="
echo "🧪 Validador de Configuración N8N+Gemini"
echo "=========================================="
echo ""

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test counters
PASSED=0
FAILED=0

# Function para test
test_endpoint() {
    local description=$1
    local url=$2
    local method=$3
    local headers=$4
    
    echo -n "Testing: $description... "
    
    if [ "$method" = "GET" ]; then
        response=$(curl -s -o /dev/null -w "%{http_code}" -X GET "$url" $headers)
    else
        response=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$url" $headers)
    fi
    
    if [ "$response" = "200" ] || [ "$response" = "202" ] || [ "$response" = "401" ]; then
        echo -e "${GREEN}✓ OK${NC} (HTTP $response)"
        ((PASSED++))
    else
        echo -e "${RED}✗ FAILED${NC} (HTTP $response)"
        ((FAILED++))
    fi
}

# Test 1: WordPress API Context
echo -e "${YELLOW}1. WordPress API Endpoints${NC}"
read -p "Enter WordPress URL (e.g., https://example.com): " WORDPRESS_URL
read -p "Enter WordPress API Key: " WORDPRESS_API_KEY

test_endpoint "Get Context" \
    "$WORDPRESS_URL/wp-json/nexgen/v1/context" \
    "GET" \
    "-H 'Authorization: Bearer $WORDPRESS_API_KEY'"

echo ""

# Test 2: Gemini API
echo -e "${YELLOW}2. Gemini API${NC}"
read -p "Enter Gemini API Key: " GEMINI_API_KEY

echo -n "Testing: Gemini API... "
response=$(curl -s -X POST \
    "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent?key=$GEMINI_API_KEY" \
    -H "Content-Type: application/json" \
    -d '{
        "contents": [{
            "parts": [{"text": "Hola"}]
        }],
        "generationConfig": {
            "temperature": 0.7,
            "maxOutputTokens": 100
        }
    }' | jq '.candidates[0].content.parts[0].text' 2>/dev/null)

if [ ! -z "$response" ] && [ "$response" != "null" ]; then
    echo -e "${GREEN}✓ OK${NC}"
    echo "Response: $response"
    ((PASSED++))
else
    echo -e "${RED}✗ FAILED${NC}"
    ((FAILED++))
fi

echo ""

# Test 3: N8N Connectivity (if provided)
echo -e "${YELLOW}3. N8N Setup (Optional)${NC}"
read -p "Enter N8N instance URL (or press Enter to skip): " N8N_URL

if [ ! -z "$N8N_URL" ]; then
    echo -n "Testing: N8N API... "
    response=$(curl -s -o /dev/null -w "%{http_code}" -X GET "$N8N_URL/api/v1/workflows")
    
    if [ "$response" = "200" ] || [ "$response" = "401" ]; then
        echo -e "${GREEN}✓ OK${NC}"
        ((PASSED++))
    else
        echo -e "${YELLOW}⚠ WARNING${NC} (HTTP $response - might need auth)"
        ((PASSED++))
    fi
fi

echo ""

# Summary
echo "=========================================="
echo "📊 Results Summary"
echo "=========================================="
echo -e "Passed: ${GREEN}$PASSED${NC}"
echo -e "Failed: ${RED}$FAILED${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ All tests passed! You're ready to go.${NC}"
else
    echo -e "${RED}✗ Some tests failed. Please review the errors above.${NC}"
fi

echo "=========================================="
