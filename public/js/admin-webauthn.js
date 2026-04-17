// ES module; @simplewebauthn/browser resolved via import map (admin webauthn pages + partial).

import {
    browserSupportsWebAuthn,
    startAuthentication,
    startRegistration,
} from "@simplewebauthn/browser";

function setStatus(element, message) {
    if (element) element.textContent = message;
}

function setButtonState(button, disabled) {
    if (button) button.disabled = disabled;
}

function getConfig() {
    const config = window.AdminWebAuthnConfig ?? {};

    return {
        mode: config.mode ?? null,
        csrfToken: config.csrfToken ?? null,
        registerOptionsUrl: config.registerOptionsUrl ?? null,
        registerStoreUrl: config.registerStoreUrl ?? null,
        verifyOptionsUrl: config.verifyOptionsUrl ?? null,
        verifyStoreUrl: config.verifyStoreUrl ?? null,
        dashboardUrl: config.dashboardUrl ?? "/admin",
    };
}

function getHeaders() {
    const { csrfToken } = getConfig();

    return {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
        ...(csrfToken ? { "X-CSRF-TOKEN": csrfToken } : {}),
    };
}

async function parseResponse(response) {
    const contentType = response.headers.get("content-type") || "";

    if (contentType.includes("application/json")) {
        return await response.json();
    }

    return await response.text();
}

function getErrorMessage(data, fallbackStatus) {
    if (typeof data === "object" && data !== null) {
        if (typeof data.message === "string" && data.message.trim()) {
            return data.message.trim();
        }

        if (typeof data.error === "string" && data.error.trim()) {
            return data.error.trim();
        }
    }

    if (typeof data === "string" && data.trim()) {
        return data.trim();
    }

    return `Request failed with status ${fallbackStatus}`;
}

async function postJson(url, body = {}) {
    const response = await fetch(url, {
        method: "POST",
        credentials: "same-origin",
        headers: getHeaders(),
        body: JSON.stringify(body),
    });

    const data = await parseResponse(response);

    if (!response.ok) {
        const error = new Error(getErrorMessage(data, response.status));
        error.status = response.status;
        error.payload = data;
        throw error;
    }

    return data;
}

function assertRequiredUrls(urls) {
    for (const [key, value] of Object.entries(urls)) {
        if (!value) {
            throw new Error(`Missing WebAuthn config value: ${key}`);
        }
    }
}

function normalizeErrorMessage(error, fallbackMessage) {
    if (!error) return fallbackMessage;

    if (error.name === "AbortError") {
        return "Passkey request was cancelled.";
    }

    if (typeof error.message === "string" && error.message.trim()) {
        return error.message.trim();
    }

    return fallbackMessage;
}

async function registerPasskey() {
    const status = document.getElementById("register-status");
    const button = document.getElementById("register-passkey-btn");
    const alias = document.getElementById("alias")?.value?.trim() || "";

    try {
        if (!browserSupportsWebAuthn()) {
            throw new Error("This browser does not support passkeys.");
        }

        setButtonState(button, true);
        setStatus(status, "Starting passkey registration...");

        const { registerOptionsUrl, registerStoreUrl, dashboardUrl } = getConfig();

        assertRequiredUrls({
            registerOptionsUrl,
            registerStoreUrl,
        });

        const optionsJSON = await postJson(registerOptionsUrl, { alias });
        console.log("[WebAuthn] register options", optionsJSON);

        setStatus(status, "Confirm the passkey prompt on your device...");

        const attestation = await startRegistration({ optionsJSON });
        console.log("[WebAuthn] register attestation", attestation);

        setStatus(status, "Saving your passkey...");

        const result = await postJson(registerStoreUrl, {
            ...attestation,
            alias,
        });

        console.log("[WebAuthn] register result", result);

        if (!result?.ok) {
            throw new Error(result?.message || "Registration failed.");
        }

        setStatus(status, "Passkey registered successfully.");
        window.location.assign(result.redirect || dashboardUrl);
    } catch (error) {
        console.error("[WebAuthn] register error", {
            name: error?.name,
            message: error?.message,
            status: error?.status,
            payload: error?.payload,
            error,
        });

        setStatus(
            status,
            normalizeErrorMessage(error, "Passkey registration failed.")
        );
    } finally {
        setButtonState(button, false);
    }
}

async function verifyPasskey() {
    const status = document.getElementById("verify-status");
    const button = document.getElementById("verify-passkey-btn");

    try {
        if (!browserSupportsWebAuthn()) {
            throw new Error("This browser does not support passkeys.");
        }

        setButtonState(button, true);
        setStatus(status, "Starting passkey verification...");

        const { verifyOptionsUrl, verifyStoreUrl, dashboardUrl } = getConfig();

        assertRequiredUrls({
            verifyOptionsUrl,
            verifyStoreUrl,
        });

        const optionsJSON = await postJson(verifyOptionsUrl);
        console.log("[WebAuthn] verify options", optionsJSON);

        setStatus(status, "Confirm the passkey prompt on your device...");

        const assertion = await startAuthentication({ optionsJSON });
        console.log("[WebAuthn] verify assertion", assertion);

        setStatus(status, "Verifying your passkey...");

        const result = await postJson(verifyStoreUrl, assertion);
        console.log("[WebAuthn] verify result", result);

        if (!result?.ok) {
            throw new Error(result?.message || "Verification failed.");
        }

        setStatus(status, "Passkey verified successfully.");
        window.location.assign(result.redirect || dashboardUrl);
    } catch (error) {
        console.error("[WebAuthn] verify error", {
            name: error?.name,
            message: error?.message,
            status: error?.status,
            payload: error?.payload,
            error,
        });

        setStatus(
            status,
            normalizeErrorMessage(error, "Passkey verification failed.")
        );
    } finally {
        setButtonState(button, false);
    }
}

document.addEventListener("DOMContentLoaded", () => {
    const config = getConfig();

    console.log("[WebAuthn] DOM loaded", {
        ...config,
        csrfToken: config.csrfToken ? "[present]" : "[missing]",
        origin: window.location.origin,
    });

    const registerBtn = document.getElementById("register-passkey-btn");
    const verifyBtn = document.getElementById("verify-passkey-btn");

    if (config.mode === "register" && registerBtn) {
        registerBtn.addEventListener("click", registerPasskey);
    }

    if (config.mode === "verify" && verifyBtn) {
        verifyBtn.addEventListener("click", verifyPasskey);
    }
});