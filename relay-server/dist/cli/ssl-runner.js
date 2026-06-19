#!/usr/bin/env node
import { execSync } from "node:child_process";
import { issueSslAcme, issueSslCertbot, renewSsl } from "./ssl.js";
import { renderAllNginx } from "./nginx.js";
import { loadInstallEnv } from "./paths.js";
loadInstallEnv();
const [, , action, arg1, arg2, arg3] = process.argv;
try {
    if (action === "issue") {
        const domain = arg1;
        const method = (arg2 || "certbot");
        const email = arg3 || "";
        if (method === "acme")
            issueSslAcme(domain, email);
        else
            issueSslCertbot(domain, email);
        renderAllNginx();
        try {
            execSync("sudo nginx -t && sudo systemctl reload nginx", { stdio: "inherit", shell: "/bin/bash" });
        }
        catch {
            /* optional */
        }
    }
    else if (action === "renew") {
        renewSsl((arg1 || "certbot"));
        renderAllNginx();
    }
    else {
        console.error("usage: ssl-runner issue <domain> [certbot|acme] [email]");
        process.exit(1);
    }
}
catch (e) {
    console.error(e);
    process.exit(1);
}
