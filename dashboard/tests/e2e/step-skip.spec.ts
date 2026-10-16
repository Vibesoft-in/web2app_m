import { test, expect } from "@playwright/test";

test.describe("Wizard Step Guard & Skip Attempt Redirects", () => {
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
  });

  test("redirects to current_step if user attempts to skip ahead via direct URL manipulation", async ({ page }) => {
    // App is currently only at connect step, later steps locked
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
            name: "Botanical Goods",
            platform: "woocommerce",
            status: "draft",
          },
        }),
      });
    });

    // Attempt direct deep-link to step 7 (preview)
    await page.goto(`/dashboard/apps/${appId}/setup/preview`);

    // Verify redirected back to allowed current_step (connect)
    await expect(page).toHaveURL(new RegExp(`/dashboard/apps/${appId}/setup/connect`));
    await expect(page.getByRole("heading", { name: /Connect Your WooCommerce Store/i })).toBeVisible();

    // Also attempt deep-link to step 10 (build)
    await page.goto(`/dashboard/apps/${appId}/setup/build`);
    await expect(page).toHaveURL(new RegExp(`/dashboard/apps/${appId}/setup/connect`));
  });

  test("allows navigation to allowed steps and permits going backwards", async ({ page }) => {
    // App has completed through theme, current is branding
    await page.route(`**/api/apps/${appId}/setup-state`, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          current_step: "branding",
          allowed_steps: ["platform", "connect", "analysis", "features", "theme", "branding"],
          completed_steps: ["platform", "connect", "analysis", "features", "theme"],
          progress_percent: 60,
          app: {
            ulid: appId,
            name: "Botanical Goods",
            platform: "woocommerce",
            status: "draft",
          },
        }),
      });
    });

    // Mock theme query
    await page.route(`**/api/apps/${appId}/themes`, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          themes: [
            { id: 1, slug: "theme_a", name: "Modern Minimal" },
            { id: 2, slug: "theme_b", name: "Bold Commerce" },
            { id: 3, slug: "theme_c", name: "Elegant Boutique" },
          ],
        }),
      });
    });

    // Mock draft config query
    await page.route(`**/api/apps/${appId}/config/draft`, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          config: {
            theme: {
              colors: { primary: "#E91E63", secondary: "#111111" },
              font: "Poppins",
              radius: 12,
            },
          },
        }),
      });
    });

    // Navigate to allowed step 'branding' -> stays on branding
    await page.goto(`/dashboard/apps/${appId}/setup/branding`);
    await expect(page).toHaveURL(new RegExp(`/dashboard/apps/${appId}/setup/branding`));
    await expect(page.getByRole("heading", { name: /Branding & Visual Identity/i })).toBeVisible();

    // User can navigate backwards to 'theme' (which is in allowed_steps)
    await page.getByText("Theme", { exact: true }).click();
    await expect(page).toHaveURL(new RegExp(`/dashboard/apps/${appId}/setup/theme`));
  });
});
