import { test, expect } from "@playwright/test";

test.describe("Client Signup Flow", () => {
  test("renders signup form and enforces minimum 10-character password", async ({ page }) => {
    await page.goto("/signup");

    await expect(page.getByRole("heading", { name: /Start your free 14-day trial/i })).toBeVisible();

    // Fill form with weak password (< 10 chars)
    await page.getByPlaceholder("Jane Doe").fill("Alice Owner");
    await page.getByPlaceholder("Glow Beauty Naturals").fill("Botanical Goods Ltd");
    await page.getByPlaceholder("jane@glowbeauty.com").fill("alice@botanicals.com");
    await page.locator('input[name="password"]').fill("short9!");
    await page.locator('input[name="password_confirmation"]').fill("short9!");

    await page.getByRole("button", { name: /Start Free Trial/i }).click();

    // Expect zod validation error
    await expect(page.getByText(/Password must be at least 10 characters/i)).toBeVisible();
  });

  test("submits valid signup and redirects to verify email notification", async ({ page }) => {
    // Intercept signup API call
    await page.route("**/api/auth/signup", async (route) => {
      await route.fulfill({
        status: 201,
        contentType: "application/json",
        body: JSON.stringify({
          message: "Registration successful. Please verify your email address.",
        }),
      });
    });

    await page.goto("/signup");

    await page.getByPlaceholder("Jane Doe").fill("Alice Owner");
    await page.getByPlaceholder("Glow Beauty Naturals").fill("Botanical Goods Ltd");
    await page.getByPlaceholder("jane@glowbeauty.com").fill("alice@botanicals.com");
    await page.locator('input[name="password"]').fill("SecurePassword123!");
    await page.locator('input[name="password_confirmation"]').fill("SecurePassword123!");

    await page.getByRole("button", { name: /Start Free Trial/i }).click();

    // Expect redirect to verify-email
    await expect(page).toHaveURL(/.*\/verify-email\?email=alice%40botanicals\.com/);
    await expect(page.getByRole("heading", { name: /Verify your email address/i })).toBeVisible();
  });
});
