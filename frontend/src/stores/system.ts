import { defineStore } from "pinia";

type HealthState = "idle" | "loading" | "ok" | "error";

type HealthResponse = {
  status: string;
};

const apiBaseUrl = (
  import.meta.env.VITE_API_BASE_URL || "http://ordem-de-servico.test"
).replace(/\/$/, "");

export const useSystemStore = defineStore("system", {
  state: () => ({
    healthState: "idle" as HealthState,
    healthPayload: null as HealthResponse | null,
    lastCheckedAt: null as string | null,
  }),
  getters: {
    healthEndpoint: () => `${apiBaseUrl}/api/v1/health`,
    docsUrl: () => `${apiBaseUrl}/docs`,
  },
  actions: {
    async checkHealth() {
      this.healthState = "loading";

      try {
        const response = await fetch(this.healthEndpoint, {
          headers: {
            Accept: "application/json",
          },
        });

        if (!response.ok) {
          throw new Error(`Unexpected status ${response.status}`);
        }

        this.healthPayload = (await response.json()) as HealthResponse;
        this.healthState = this.healthPayload.status === "ok" ? "ok" : "error";
        this.lastCheckedAt = new Date().toISOString();
      } catch {
        this.healthPayload = null;
        this.healthState = "error";
      }
    },
  },
});
