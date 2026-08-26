import { test, expect, chromium } from '@playwright/test';

test.describe('Form Layout 11 - Booking Flow with Error Capture', () => {
  let browser;
  let context;
  let page;
  const errors = [];
  const consoleMessages = [];

  test.beforeAll(async () => {
    browser = await chromium.launch();
  });

  test.afterAll(async () => {
    await browser.close();
  });

  test('should complete form layout 11 booking without errors', async () => {
    context = await browser.newContext();
    page = await context.newPage();

    // Capture console messages
    page.on('console', msg => {
      const logEntry = {
        type: msg.type(),
        text: msg.text(),
        location: msg.location()
      };
      consoleMessages.push(logEntry);
      console.log(`[${msg.type().toUpperCase()}] ${msg.text()}`);
    });

    // Capture page errors
    page.on('pageerror', error => {
      errors.push({
        type: 'PAGE_ERROR',
        message: error.message,
        stack: error.stack
      });
      console.error('[PAGE_ERROR]', error);
    });

    // Capture request failures
    page.on('requestfailed', request => {
      errors.push({
        type: 'REQUEST_FAILED',
        url: request.url(),
        failure: request.failure()
      });
      console.error('[REQUEST_FAILED]', request.url(), request.failure());
    });

    try {
      // Navigate to the booking page
      console.log('Navigating to Form Layout 11...');
      await page.goto('/', {
        waitUntil: 'networkidle',
        timeout: 30000
      });

      // Wait for the form to load
      await page.waitForSelector('#appointment-form', { timeout: 10000 });
      console.log('✓ Form loaded successfully');

      // Step 1: Select category
      console.log('\n--- STEP 1: Select Category ---');
      const categories = await page.$$('.fl11-service-card');
      if (categories.length > 0) {
        await categories[0].click();
        await page.waitForTimeout(500);
        console.log('✓ Category selected');
      }

      // Step 1: Select service
      console.log('\n--- STEP 1: Select Service ---');
      const serviceSelect = await page.$('#serviceSelect');
      if (serviceSelect) {
        const options = await page.$$eval('#serviceSelect option', opts => opts.map(o => o.value).filter(v => v));
        if (options.length > 0) {
          await page.selectOption('#serviceSelect', options[0]);
          await page.waitForTimeout(500);
          console.log('✓ Service selected:', options[0]);
        }
      }

      // Step 1: Select first available staff and location (single-option seeds auto-apply)
      for (const sel of ['#staffSelect', '#locationSelect']) {
        const el = await page.$(sel);
        if (el) {
          const vals = await page.$$eval(sel + ' option', os => os.map(o => o.value).filter(v => v));
          if (vals.length > 0) {
            await page.selectOption(sel, vals[0]);
            await page.waitForTimeout(300);
            console.log('✓ Selected', sel, '=>', vals[0]);
          }
        }
      }

      // Click continue to Step 2
      console.log('\n--- Moving to STEP 2 ---');
      await page.click('#fl11ContinueStep1');
      await page.waitForTimeout(1000);

      // Step 2: Select date
      console.log('\n--- STEP 2: Select Date ---');
      const datepicker = await page.$('#datepicker');
      if (datepicker) {
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        const dateStr = tomorrow.toISOString().split('T')[0];
        await page.fill('#datepicker', dateStr);
        await page.waitForTimeout(500);
        console.log('✓ Date selected:', dateStr);
      }

      // Step 2: Select time
      console.log('\n--- STEP 2: Select Time ---');
      const timeSlots = await page.$$('input[name="duration"]');
      if (timeSlots.length > 0) {
        await timeSlots[0].click();
        await page.waitForTimeout(500);
        console.log('✓ Time slot selected');
      }

      // Click continue to Step 3 or 4
      console.log('\n--- Moving to next step ---');
      await page.click('#fl11ContinueStep2');
      await page.waitForTimeout(1000);

      // Step 3/4: Handle user details
      console.log('\n--- STEP 3/4: Fill User Details ---');
      const guestNameField = await page.$('#guest_name');
      if (guestNameField) {
        await page.fill('#guest_name', 'Test User');
        await page.fill('#guest_email', 'test@example.com');
        await page.fill('#guest_contact', '+923001234567');
        console.log('✓ Guest details filled');
      }

      // Step 3: Continue to next
      const continueStep3 = await page.$('#fl11ContinueStep3');
      if (continueStep3) {
        await page.click('#fl11ContinueStep3');
        await page.waitForTimeout(1000);
        console.log('✓ Step 3 completed');
      }

      // Step 4: Continue to payment
      console.log('\n--- Moving to STEP 5 (Payment) ---');
      const continueStep4 = await page.$('#fl11ContinueStep4');
      if (continueStep4) {
        await page.click('#fl11ContinueStep4');
        await page.waitForTimeout(1500);
        console.log('✓ Moved to payment step');
      }

      // Step 5: Click confirm booking button
      console.log('\n--- STEP 5: Confirm Booking ---');
      const confirmBtn = await page.$('#fl11SubmitBtn');
      if (confirmBtn) {
        console.log('Clicking Confirm Booking button...');
        await confirmBtn.click();
        console.log('Button clicked, waiting for response...');
        
        // Wait to see if we move to step 6
        await page.waitForTimeout(3000);
        
        // Check if step 6 is visible
        const step6Visible = await page.$('#fl11Step6:visible');
        if (step6Visible) {
          console.log('✓ Step 6 (Confirmation) displayed successfully');
        } else {
          console.error('✗ Step 6 NOT displayed');
          
          // Check for errors in page
          const networkResponse = await page.evaluate(() => {
            return {
              readyState: document.readyState,
              step6Display: document.getElementById('fl11Step6')?.style.display,
              step5Display: document.getElementById('fl11Step5')?.style.display,
              errors: window.__errors || []
            };
          });
          console.error('Page state:', networkResponse);
        }
      }

      // Check for any JavaScript errors
      const pageErrors = await page.evaluate(() => {
        return {
          hasErrors: typeof window.__errors !== 'undefined',
          errors: window.__errors || [],
          consoleErrors: window.__consoleErrors || []
        };
      });

      if (pageErrors.hasErrors && pageErrors.errors.length > 0) {
        console.error('\n!!! ERRORS DETECTED !!!');
        pageErrors.errors.forEach(err => console.error('- ', err));
      }

    } catch (error) {
      errors.push({
        type: 'TEST_ERROR',
        message: error.message,
        stack: error.stack
      });
      console.error('Test error:', error);
    }

    // Print summary
    console.log('\n\n=== TEST SUMMARY ===');
    console.log('Console messages:', consoleMessages.length);
    console.log('Errors captured:', errors.length);
    
    if (errors.length > 0) {
      console.log('\n=== ERRORS ===');
      errors.forEach((err, idx) => {
        console.log(`\n${idx + 1}. ${err.type}`);
        console.log('   Message:', err.message || err.failure?.errorText || 'N/A');
        if (err.stack) console.log('   Stack:', err.stack.split('\n')[0]);
      });
    }

    await context.close();
  });
});
