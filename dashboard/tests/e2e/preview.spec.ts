import { test, expect } from "@playwright/test";

test.describe("Wizard Step 7: Live Phone-Frame Preview & Debounced Autosave", () => {
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

    // Mock setup state: preview is allowed
    await page.route(`**/api/apps/${appId}/setup-state`, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          current_step: "preview",
          allowed_steps: [
            "platform",
            "connect",
            "analysis",
            "features",
            "theme",
            "branding",
            "preview",
          ],
          completed_steps: [
            "platform",
            "connect",
            "analysis",
            "features",
            "theme",
            "branding",
          ],
          progress_percent: 70,
          app: {
            ulid: appId,
            name: "Botanical Goods",
            platform: "woocommerce",
            status: "draft",
          },
        }),
      });
    });

    // Mock preview products
    await page.route(`**/api/apps/${appId}/preview-data`, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          products: [
            {
              id: 101,
              title: "Hydrating Rose Toner",
              price: "45.00",
              images: [],
            },
            {
              id: 102,
              title: "Bakuchiol Miracle Serum",
              price: "68.00",
              images: [],
            },
          ],
          categories: [
            { id: 1, name: "Toners" },
            { id: 2, name: "Serums" },
          ],
        }),
      });
    });

    // Mock initial draft config
    await page.route(`**/api/apps/${appId}/config/draft`, async (route) => {
      if (route.request().method() === "GET") {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({
            config: {
              schema_version: 1,
              app: { name: "Glow Naturals", currency: "USD" },
              theme: {
                layout: "theme_a",
                colors: {
                  primary: "#E91E63",
                  secondary: "#222222",
                  background: "#FFFFFF",
                  surface: "#FAFAFA",
                  text: "#111111",
                },
                radius: 12,
                font: "Poppins",
                nav: "bottom",
              },
              features: {
                wishlist: true,
                otp_login: true,
                google_login: true,
                notifications: true,
                reviews: false,
              },
              home: [
                {
                  type: "banner",
                  items: [{ image: "https://assets.webintoapp.com/banner.jpg", link: "category:all" }],
                },
                { type: "categories" },
                { type: "product_rail", title: "Best Sellers", source: "recent" },
              ],
            },
          }),
        });
      } else {
        // Handled per test if needed
        await route.continue();
      }
    });
  });

  test("renders phone frame with real store catalog products and categories", async ({ page }) => {
    await page.goto(`/dashboard/apps/${appId}/setup/preview`);

    await expect(page.getByRole("heading", { name: /Interactive Phone Preview/i })).toBeVisible();

    // Verify products rendered inside phone frame
    await expect(page.getByText("Hydrating Rose Toner")).toBeVisible();
    await expect(page.getByText("$45.00")).toBeVisible();
    await expect(page.getByText("Bakuchiol Miracle Serum")).toBeVisible();
    await expect(page.getByText("$68.00")).toBeVisible();

    // Categories in phone frame
    await expect(page.getByText("Toners")).toBeVisible();
    await expect(page.getByText("Serums")).toBeVisible();
  });

  test("updates phone frame live and triggers 400ms debounced autosave PUT request", async ({ page }) => {
    let draftPutReceived = false;
    let putPayload: any = null;

    // Track PUT requests to draft config
    await page.route(`**/api/apps/${appId}/config/draft`, async (route) => {
      if (route.request().method() === "PUT") {
        draftPutReceived = true;
        putPayload = JSON.parse(route.request().postData() || "{}");
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({ message: "Draft updated successfully" }),
        });
      } else {
        await route.fallback();
      }
    });

    await page.goto(`/dashboard/apps/${appId}/setup/preview`);

    // Target the App Display Name input field
    const nameInput = page.locator('input[type="text"]').filter({ hasText: "" }).first();
    await nameInput.fill("Luxe Botanical Studio");

    // Live phone frame updates immediately in DOM before debounce completes
    await expect(page.locator("span.font-bold.text-sm.tracking-tight", { hasText: "Luxe Botanical Studio" })).toBeVisible();

    // Wait 500ms to allow 400ms debounce timer to fire
    await page.waitForTimeout(550);

    // Assert debounced PUT request was made with the updated name
    expect(draftPutReceived).toBe(true);
    expect(putPayload?.app?.name).toBe("Luxe Botanical Studio");
  });

  test("publishes configuration and proceeds to next step", async ({ page }) => {
    let published = false;

    await page.route(`**/api/apps/${appId}/config/publish`, async (route) => {
      published = true;
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          message: "Configuration published as active snapshot.",
          version: 2,
        }),
      });
    });

    await page.goto(`/dashboard/apps/${appId}/setup/preview`);

    const publishBtn = page.getByRole("button", { name: /Publish Configuration/i });
    await expect(publishBtn).toBeVisible();
    await publishBtn.click();

    // Expect button state to update to Published!
    await expect(page.getByText(/Published!/i)).toBeVisible();
    expect(published).toBe(true);
  });
});
