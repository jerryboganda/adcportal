import { test, expect } from '@playwright/test';

// Set ignore SSL errors for self-signed certs (Laragon)
test.use({ ignoreHTTPSErrors: true });

test.describe('Form Layout 11 - Complete Booking Flow', () => {
    let page;
    let consoleMessages = [];
    let errors = [];

    test.beforeEach(async ({ context }) => {
        page = await context.newPage();
        
        // Capture console messages
        page.on('console', msg => {
            const logEntry = {
                type: msg.type(),
                text: msg.text(),
                location: msg.location()
            };
            consoleMessages.push(logEntry);
            console.log(`[${msg.type()}] ${msg.text()}`);
        });
        
        // Capture page errors
        page.on('pageerror', error => {
            const errorEntry = {
                message: error.message,
                stack: error.stack
            };
            errors.push(errorEntry);
            console.error('PAGE ERROR:', error.message);
        });
        
        // Capture request failures
        page.on('requestfailed', request => {
            console.error('REQUEST FAILED:', request.url(), request.failure().errorText);
        });
        
        // Navigate to the booking form
        console.log('\n=== Starting Form Layout 11 Test ===\n');
    });

    test.afterEach(async () => {
        // Print test summary
        console.log('\n=== Test Summary ===');
        console.log('Console Messages:', consoleMessages.length);
        console.log('Errors:', errors.length);
        if (errors.length > 0) {
            console.log('Error Details:');
            errors.forEach(err => console.log('  -', err.message));
        }
        
        if (page) {
            await page.close();
        }
    });

    test('Complete booking flow from Step 1 to Step 6 (Confirmation)', async () => {
        try {
            // Navigate to appointment form
            // Use the existing AMAD business which has services and data
            console.log('\n[TEST] Navigating to booking form...');
            await page.goto('/appointments/amad-diagnostic-centre-gujranwala', {
                waitUntil: 'networkidle',
                timeout: 30000
            });
            
            console.log('[TEST] Page loaded, waiting for form elements...');
            
            // Wait for Step 1 content to be visible
            await page.waitForSelector('#fl11Step1', { timeout: 10000 });
            console.log('[TEST] ✓ Step 1 found');
            
            // STEP 1: Category Selection
            console.log('\n[TEST] === STEP 1: Service Selection ===');
            
            // Wait for category select and select first category
            await page.waitForSelector('#categorySelect', { timeout: 5000 });
            const categories = await page.locator('#categorySelect').evaluate(el => {
                return Array.from(el.options).map(opt => opt.value);
            });
            console.log('[TEST] Available categories:', categories);
            
            if (categories.length > 1) {
                // Select the first non-empty category
                await page.selectOption('#categorySelect', categories[1]);
                console.log('[TEST] ✓ Category selected:', categories[1]);
                
                // Wait for services to load
                await page.waitForTimeout(500);
                
                // Select first available service
                const services = await page.locator('#serviceSelect').evaluate(el => {
                    return Array.from(el.options).filter(opt => opt.value).map(opt => opt.value);
                });
                console.log('[TEST] Available services:', services);
                
                if (services.length > 0) {
                    await page.selectOption('#serviceSelect', services[0]);
                    console.log('[TEST] ✓ Service selected:', services[0]);
                    
                    // Click Continue button
                    await page.click('#fl11ContinueStep1');
                    await page.waitForTimeout(500);
                    console.log('[TEST] ✓ Step 1 completed - moved to Step 2');
                } else {
                    throw new Error('No services available');
                }
            } else {
                throw new Error('No categories available');
            }
            
            // STEP 2: Date and Time Selection
            console.log('\n[TEST] === STEP 2: Date & Time Selection ===');
            
            // Wait for datepicker
            await page.waitForSelector('#datepicker', { timeout: 5000 });
            
            // Pick a date (tomorrow)
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            const dateStr = tomorrow.toISOString().split('T')[0];
            
            await page.fill('#datepicker', dateStr);
            console.log('[TEST] ✓ Date selected:', dateStr);
            
            // Wait for time slots to load
            await page.waitForTimeout(1000);
            
            // Select first available time slot
            const timeSlots = await page.locator('input[name="duration"]').count();
            console.log('[TEST] Available time slots:', timeSlots);
            
            if (timeSlots > 0) {
                await page.click('input[name="duration"]');
                console.log('[TEST] ✓ Time slot selected');
                
                // Click Continue button
                await page.click('#fl11ContinueStep2');
                await page.waitForTimeout(500);
                console.log('[TEST] ✓ Step 2 completed - moved to next step');
            } else {
                console.warn('[TEST] ⚠ No time slots available');
            }
            
            // Check if Step 3 (Additional Fields) is visible
            const hasStep3 = await page.locator('#fl11Step3').isVisible().catch(() => false);
            
            if (hasStep3) {
                console.log('\n[TEST] === STEP 3: Additional Fields ===');
                // Skip Step 3 if it appears
                const continueBtn3 = await page.locator('#fl11ContinueStep3').isVisible().catch(() => false);
                if (continueBtn3) {
                    await page.click('#fl11ContinueStep3');
                    await page.waitForTimeout(500);
                    console.log('[TEST] ✓ Step 3 completed');
                }
            }
            
            // STEP 4: Customer Information
            console.log('\n[TEST] === STEP 4: Customer Information ===');
            
            // Check which tab is active
            const activeTab = await page.locator('.fl11-user-tab.active').getAttribute('data-tab');
            console.log('[TEST] Active user tab:', activeTab);
            
            if (activeTab === 'guest-user') {
                // Fill guest user form
                await page.fill('#guest_name', 'Test Customer');
                await page.fill('#guest_email', 'test@example.com');
                await page.fill('#guest_contact', '+1234567890');
                console.log('[TEST] ✓ Guest user details filled');
            } else if (activeTab === 'existing-user') {
                // Try to select an existing user or fill form
                const existingUsers = await page.locator('#existing_customer').evaluate(el => {
                    if (!el) return null;
                    return Array.from(el.options).filter(opt => opt.value).map(opt => opt.value);
                }).catch(() => null);
                
                if (existingUsers && existingUsers.length > 0) {
                    await page.selectOption('#existing_customer', existingUsers[0]);
                    console.log('[TEST] ✓ Existing user selected');
                } else {
                    console.warn('[TEST] ⚠ No existing users available, trying new user');
                    await page.click('[data-tab="new-user"]');
                    await page.fill('#new_name', 'New Test User');
                    await page.fill('#new_email', 'newuser@example.com');
                    await page.fill('#new_contact', '+0987654321');
                    await page.fill('#new_password', 'Password@123');
                    console.log('[TEST] ✓ New user form filled');
                }
            }
            
            // Click Continue to move to Step 5
            await page.click('#fl11ContinueStep4');
            await page.waitForTimeout(500);
            console.log('[TEST] ✓ Step 4 completed - moved to Step 5');
            
            // STEP 5: Payment Review
            console.log('\n[TEST] === STEP 5: Payment Review ===');
            
            // Wait for Step 5 to be visible
            await page.waitForSelector('#fl11Step5', { timeout: 5000 });
            console.log('[TEST] ✓ Step 5 (Payment) displayed');
            
            // STEP 6: Form Submission (Confirm Booking)
            console.log('\n[TEST] === STEP 6: Form Submission ===');
            
            // Click submit button
            const submitBtn = await page.locator('#fl11SubmitBtn');
            console.log('[TEST] Submitting form...');
            
            // Wait for submission to complete
            const responsePromise = page.waitForResponse(
                response => response.url().includes('appointment-book') && response.status() === 200,
                { timeout: 15000 }
            ).catch(() => null);
            
            await submitBtn.click();
            
            const response = await responsePromise;
            if (response) {
                const responseData = await response.json();
                console.log('[TEST] ✓ Form submitted successfully');
                console.log('[TEST] Response status:', responseData.status);
                console.log('[TEST] Response message:', responseData.message);
                if (responseData.url) {
                    console.log('[TEST] Redirect URL:', responseData.url);
                }
            } else {
                console.warn('[TEST] ⚠ No confirmation response received');
            }
            
            // STEP 6: Confirmation Page
            console.log('\n[TEST] === STEP 6: Booking Confirmation ===');
            
            // Wait for confirmation step to appear (Step 6)
            const step6Visible = await page.waitForSelector('#fl11Step6', { timeout: 5000 }).catch(() => null);
            
            if (step6Visible) {
                console.log('[TEST] ✓ Step 6 (Confirmation) displayed');
                
                // Check for booking details
                const bookingNumber = await page.locator('#bookingNumber').textContent().catch(() => null);
                const bookingService = await page.locator('#bookingService').textContent().catch(() => null);
                const bookingDateTime = await page.locator('#bookingDateTime').textContent().catch(() => null);
                const bookingLocation = await page.locator('#bookingLocation').textContent().catch(() => null);
                
                console.log('[TEST] Booking Details:');
                console.log('  - Number:', bookingNumber);
                console.log('  - Service:', bookingService);
                console.log('  - DateTime:', bookingDateTime);
                console.log('  - Location:', bookingLocation);
                
                // Check for QR code
                const qrCode = await page.locator('#appointmentQrCode canvas').isVisible().catch(() => false);
                console.log('[TEST] ✓ QR Code:', qrCode ? 'Visible' : 'Not visible');
            } else {
                console.error('[TEST] ✗ Confirmation page did not appear');
                
                // Take screenshot for debugging
                await page.screenshot({ path: 'test-failure.png' });
                console.log('[TEST] Screenshot saved: test-failure.png');
                
                throw new Error('Form submission did not complete - Confirmation page not displayed');
            }
            
            console.log('\n[TEST] ✅ All steps completed successfully!');
            
        } catch (error) {
            console.error('\n[TEST] ❌ Test failed:', error.message);
            await page.screenshot({ path: 'test-error.png' });
            console.log('[TEST] Error screenshot saved: test-error.png');
            throw error;
        }
    });
});
