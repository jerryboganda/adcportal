import { test, expect } from '@playwright/test';

test.use({ ignoreHTTPSErrors: true });

test.describe('Form Layout 11 - Complete End-to-End Booking', () => {
    let page;
    let consoleErrors = [];

    test.beforeEach(async ({ context }) => {
        page = await context.newPage();
        
        // Capture errors
        page.on('console', msg => {
            if (msg.type() === 'error') {
                consoleErrors.push(msg.text());
            }
            if (msg.text().includes('FL11') || msg.type() === 'error') {
                console.log(`[${msg.type()}] ${msg.text()}`);
            }
        });
    });

    test.afterEach(async () => {
        if (page) {
            await page.close();
        }
    });

    test('Complete booking flow with form submission and confirmation', async () => {
        try {
            console.log('\n=== Starting Complete Booking Test ===\n');
            
            // Navigate to form
            await page.goto('/', {
                waitUntil: 'load',
                timeout: 30000
            });
            
            await page.waitForSelector('#appointment-form', { timeout: 5000 });
            console.log('[STEP 0] Form loaded successfully');
            
            // STEP 1: Select category and service
            const categories = await page.locator('#categorySelect option').count();
            console.log(`[STEP 1] Found ${categories} categories`);
            
            if (categories > 1) {
                // Select first non-empty category
                const categoryOptions = await page.locator('#categorySelect option').evaluateAll(els => 
                    els.filter(e => e.value && e.value !== '').map(e => e.value)
                );
                
                if (categoryOptions.length > 0) {
                    await page.selectOption('#categorySelect', categoryOptions[0]);
                    console.log('[STEP 1] Category selected:', categoryOptions[0]);
                    
                    // Wait for services to load
                    await page.waitForTimeout(800);
                    
                    const services = await page.locator('#serviceSelect option').evaluateAll(els =>
                        els.filter(e => e.value && e.value !== '').map(e => e.value)
                    );
                    
                    if (services.length > 0) {
                        await page.selectOption('#serviceSelect', services[0]);
                        console.log('[STEP 1] Service selected:', services[0]);

                        // Staff + location are required by the booking validator.
                        // The staff dropdown is populated via AJAX (get-staff-data) only once BOTH
                        // service and location are chosen, so pick location first, then staff.
                        const locVals = await page.$$eval('#locationSelect option', os => os.map(o => o.value).filter(v => v));
                        if (locVals.length > 0) {
                            await page.selectOption('#locationSelect', locVals[0]);
                            await page.waitForTimeout(300);
                            console.log('[STEP 1] Selected #locationSelect =>', locVals[0]);
                        }
                        await page.waitForFunction(() => {
                            const s = document.getElementById('staffSelect');
                            return s && Array.from(s.options).some(o => o.value);
                        }, { timeout: 8000 }).catch(() => {});
                        const staffVals = await page.$$eval('#staffSelect option', os => os.map(o => o.value).filter(v => v));
                        if (staffVals.length > 0) {
                            await page.selectOption('#staffSelect', staffVals[0]);
                            await page.waitForTimeout(200);
                            console.log('[STEP 1] Selected #staffSelect =>', staffVals[0]);
                        } else {
                            console.log('[STEP 1] WARNING: no staff options loaded');
                        }

                        await page.click('#fl11ContinueStep1');
                        await page.waitForTimeout(500);
                        console.log('[STEP 1] ✓ Completed');
                    }
                }
            }
            
            // STEP 2: Select date and time
            console.log('[STEP 2] Selecting date and time...');
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            const dateStr = tomorrow.toISOString().split('T')[0];
            
            // #datepicker is a readonly bootstrap-datepicker input; drive it via the widget API.
            await page.evaluate(() => {
                const d = new Date();
                d.setDate(d.getDate() + 1);
                const $el = (window.jQuery || window.$);
                if ($el) {
                    $el('#datepicker').datepicker('setDate', d);
                    $el('#datepicker').trigger('changeDate');
                }
            });
            await page.waitForTimeout(800);
            
            const timeSlots = await page.locator('input[name="duration"]').count();
            if (timeSlots > 0) {
                await page.click('input[name="duration"]');
                console.log('[STEP 2] Time selected');
                await page.click('#fl11ContinueStep2');
                await page.waitForTimeout(500);
                console.log('[STEP 2] ✓ Completed');
            }
            
            // Check for Step 3
            const step3Visible = await page.locator('#fl11Step3:visible').count() > 0;
            if (step3Visible) {
                console.log('[STEP 3] Found additional fields');
                const continue3Btn = await page.locator('#fl11ContinueStep3').isVisible().catch(() => false);
                if (continue3Btn) {
                    await page.click('#fl11ContinueStep3');
                    await page.waitForTimeout(500);
                    console.log('[STEP 3] ✓ Completed');
                }
            }
            
            // STEP 4: User information
            console.log('[STEP 4] Filling user information...');
            const activeTab = await page.locator('.fl11-user-tab.active').getAttribute('data-tab');
            console.log('[STEP 4] Using tab:', activeTab);
            
            if (activeTab === 'guest-user') {
                await page.fill('#guest_name', 'Test User');
                await page.fill('#guest_email', 'test@example.com');
                await page.fill('#guest_contact', '+1234567890');
                console.log('[STEP 4] Guest user filled');
            } else if (activeTab === 'new-user') {
                await page.fill('#new_name', 'New Test User');
                await page.fill('#new_email', 'newtest@example.com');
                await page.fill('#new_contact', '+923001234567');
                await page.fill('#new_password', 'TestPass@123');
                console.log('[STEP 4] New user filled');
            }
            
            await page.click('#fl11ContinueStep4');
            await page.waitForTimeout(500);
            console.log('[STEP 4] ✓ Completed');
            
            // STEP 5: Payment review
            console.log('[STEP 5] Payment review...');
            await page.waitForSelector('#fl11Step5:visible', { timeout: 5000 });
            console.log('[STEP 5] ✓ Payment step visible');
            
            // STEP 6: Submit form
            console.log('[STEP 6] Submitting booking...');
            const submitBtn = page.locator('#fl11SubmitBtn');
            
            // Wait for response
            let responseData = null;
            const responsePromise = page.waitForResponse(
                response => response.url().includes('appointment-book'),
                { timeout: 20000 }
            ).then(async response => {
                console.log('[STEP 6] Response received, status:', response.status());
                if (response.ok) {
                    return await response.json();
                } else {
                    const text = await response.text();
                    console.error('[STEP 6] Error response:', text);
                    throw new Error('HTTP ' + response.status());
                }
            }).catch(err => {
                console.error('[STEP 6] Response error:', err.message);
                throw err;
            });
            
            // Capture validation errors from a 422 response for diagnosis
            page.on('response', async (response) => {
                try {
                    if (response.url().includes('/booking') && response.status() === 422) {
                        console.log('[DEBUG 422 BODY]', await response.text());
                    }
                } catch (e) {}
            });

            // Click submit
            await submitBtn.click();
            console.log('[STEP 6] Form submitted');
            
            // Wait for response
            responseData = await responsePromise;
            console.log('[STEP 6] Response status:', responseData.status);
            console.log('[STEP 6] Response message:', responseData.message);
            
            if (responseData.status === 'success') {
                console.log('[STEP 6] ✓ Booking successful!');
                console.log('[STEP 6] Appointment created, response URL:', responseData.url);
                
                // Wait for confirmation step
                const confirmationVisible = await page.waitForSelector('#fl11Step6:visible', { timeout: 5000 })
                    .catch(() => null);
                
                if (confirmationVisible) {
                    console.log('[STEP 6] ✓ Confirmation page displayed');
                    
                    const bookingNum = await page.locator('#bookingNumber').textContent();
                    const service = await page.locator('#bookingService').textContent();
                    const dateTime = await page.locator('#bookingDateTime').textContent();
                    
                    console.log('[RESULT] Booking Number:', bookingNum);
                    console.log('[RESULT] Service:', service);
                    console.log('[RESULT] DateTime:', dateTime);
                    
                    console.log('\n✅ COMPLETE BOOKING FLOW SUCCESSFUL!');
                } else {
                    throw new Error('Confirmation step not displayed after successful submission');
                }
            } else {
                throw new Error(`Booking failed: ${responseData.message || 'Unknown error'}`);
            }
            
        } catch (error) {
            console.error('\n❌ Test failed:', error.message);
            console.error('Console errors:', consoleErrors);
            await page.screenshot({ path: 'booking-test-failure.png' });
            throw error;
        }
    });
});
