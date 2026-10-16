import { test, expect } from "@playwright/test";

test.describe("Wizard Step 2: Store Connection Verification", () => {
  const appId = "app_test_ulid_123";

  test.beforeEach(async ({ page }) => {
    // Mock user auth
    await page.route("**/api/auth/me", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          id: 1,
          name: "Alice Owner",
          email: "alice@botanicals.com",
          tenant_id: "tenant_123",
        }),
      });
    });

    // Mock setup state: at connect step
    await page.route(`**/api/apps/${appId}/setup-state`, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          current_step: "connect",
          allowed_steps: ["platform", "connect"],
          completed_steps: ["platform"],
          progress_percent: 20,
          app: {
            ulid: appId,
            name: "Botanical Store",
            platform: "woocommerce",
            status: "draft",
          },
        }),
      });
    });
  });

  test("displays clear error when store connection fails with NOT_HTTPS", async ({ page }) => {
    // Mock verify endpoint returning NOT_HTTPS error
    await page.route(`**/api/apps/${appId}/connection/verify`, async (route) => {
      await route.fulfill({
        status: 422,
        contentType: "application/json",
        body: JSON.stringify({
          code: "NOT_HTTPS",
          message: "The store URL must use HTTPS to connect to WooCommerce REST API.",
        }),
      });
    });

    await page.goto(`/dashboard/apps/${appId}/setup/connect`);

    await expect(page.getByRole("heading", { name: /Connect Your WooCommerce Store/i })).toBeVisible();

    // Fill form with credentials
    await page.getByPlaceholder("https://yourstore.com").fill("https://insecure-store.com");
    await page.getByPlaceholder("ck_1234567890abcdef...").fill("ck_mocked_invalid_123");
    await page.getByPlaceholder("cs_0987654321fedcba...").fill("cs_mocked_secret_456");

    await page.getByRole("button", { name: /Verify Connection/i }).click();

    // Verify error banner and troubleshooting tip
    await expect(page.getByText(/Connection Error \(NOT_HTTPS\)/i)).toBeVisible();
    await expect(page.getByText(/WooCommerce requires an SSL\/HTTPS certificate/i)).toBeVisible();
  });

  test("displays clear error when keys are invalid (INVALID_KEYS)", async ({ page }) => {
    await page.route(`**/api/apps/${appId}/connection/verify`, async (route) => {
      await route.fulfill({
        status: 422,
        contentType: "application/json",
        body: JSON.stringify({
          code: "INVALID_KEYS",
          message: "WooCommerce API returned 401 Unauthorized for these credentials.",
        }),
      });
    });

    await page.goto(`/dashboard/apps/${appId}/setup/connect`);

    await page.getByPlaceholder("https://yourstore.com").fill("https://my-woo-shop.com");
    await page.getByPlaceholder("ck_1234567890abcdef...").fill("ck_wrong_key_999");
    await page.getByPlaceholder("cs_0987654321fedcba...").fill("cs_wrong_secret_888");

    await page.getByRole("button", { name: /Verify Connection/i }).click();

    await expect(page.getByText(/Connection Error \(INVALID_KEYS\)/i)).toBeVisible();
    await expect(page.getByText(/Consumer Key or Consumer Secret is incorrect/i)).toBeVisible();
  });

  test("verifies store successfully and displays store metadata with masked key preview", async ({ page }) => {
    // Mock successful verification
    await page.route(`**/api/apps/${appId}/connection/verify`, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          status: "verified",
          store: {
            name: "Glow Botanicals Official",
            currency: "USD",
            products_count: 312,
            wc_version: "8.6.0",
          },
          connection: {
            consumer_key_preview: "ck_****cdef",
            verified_at: "2026-10-13T14:00:00Z",
          },
        }),
      });
    });

    await page.goto(`/dashboard/apps/${appId}/setup/connect`);

    await page.getByPlaceholder("https://yourstore.com").fill("https://glowbotanicals.com");
    await page.getByPlaceholder("ck_1234567890abcdef...").fill("ck_real_valid_key_cdef");
    await page.getByPlaceholder("cs_0987654321fedcba...").fill("cs_real_secret_token_123");

    await page.getByRole("button", { name: /Verify Connection/i }).click();

    // Verify success banner and metadata
    await expect(page.getByText(/Store Verified Successfully/i)).toBeVisible();
    await expect(page.getByText("Glow Botanicals Official")).toBeVisible();
    await expect(page.getByText(/312 Products/i)).toBeVisible();
    await expect(page.getByText(/8.6.0/i)).toBeVisible();

    // Check masked key preview (never raw secret)
    await expect(page.getByText("ck_****cdef")).toBeVisible();
    await expect(page.getByText(/Secret safely encrypted at rest/i)).toBeVisible();

    // Next step button appears
    await expect(page.getByRole("button", { name: /Continue to Next Step/i })).toBeVisible();
  });
});
