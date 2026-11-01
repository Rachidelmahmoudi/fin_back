/**
 * FinPharma — public/js/api-client.js
 * Client minimal pour parler à l'API JWT (Phase 4b) depuis les pages web
 * (login, register, formulaires d'établissement). Pas de framework JS
 * ajouté (Vue/React) pour ces quelques pages — vanilla JS suffit et évite
 * d'alourdir le site public avec un bundle inutile.
 *
 * Stockage : localStorage (accessToken + refreshToken). C'est un choix
 * pragmatique pour aller vite ; un vrai durcissement sécurité voudrait
 * probablement un cookie httpOnly côté web pour éviter l'exposition XSS du
 * refresh token — à revoir si/quand le site gère des comptes sensibles à
 * grande échelle (cf. limitation notée dans le README).
 */
window.FinPharmaApi = (function () {
    'use strict';

    const API_BASE = '/api/v1';

    function getAccessToken() { return localStorage.getItem('fp_access_token'); }
    function getRefreshToken() { return localStorage.getItem('fp_refresh_token'); }

    function setTokens(accessToken, refreshToken) {
        localStorage.setItem('fp_access_token', accessToken);
        if (refreshToken) { localStorage.setItem('fp_refresh_token', refreshToken); }
    }

    function clearTokens() {
        localStorage.removeItem('fp_access_token');
        localStorage.removeItem('fp_refresh_token');
    }

    function isLoggedIn() { return !!getAccessToken(); }

    async function request(path, options) {
        options = options || {};
        options.headers = Object.assign({ 'Content-Type': 'application/json' }, options.headers || {});
        const token = getAccessToken();
        if (token) { options.headers['Authorization'] = 'Bearer ' + token; }

        let response = await fetch(API_BASE + path, options);

        // Access token expiré (15 min, Phase 4b) : tente un refresh silencieux
        // une seule fois avant d'abandonner, pour éviter de déconnecter
        // l'utilisateur juste parce qu'il a rempli un formulaire lentement.
        if (response.status === 401 && getRefreshToken()) {
            const refreshed = await tryRefresh();
            if (refreshed) {
                options.headers['Authorization'] = 'Bearer ' + getAccessToken();
                response = await fetch(API_BASE + path, options);
            }
        }

        return response;
    }

    async function tryRefresh() {
        try {
            const res = await fetch(API_BASE + '/auth/refresh', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ refreshToken: getRefreshToken() }),
            });
            if (!res.ok) { clearTokens(); return false; }
            const data = await res.json();
            setTokens(data.accessToken, data.refreshToken);
            return true;
        } catch (e) {
            clearTokens();
            return false;
        }
    }

    async function login(email, password) {
        const res = await fetch(API_BASE + '/auth/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email, password: password }),
        });
        if (!res.ok) { throw new Error('login_failed'); }
        const data = await res.json();
        setTokens(data.token || data.accessToken, data.refreshToken);
        return data;
    }

    async function register(payload) {
        const res = await fetch(API_BASE + '/auth/register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            throw new Error(err.error || 'register_failed');
        }
        const data = await res.json();
        setTokens(data.accessToken, data.refreshToken);
        return data;
    }

    function logout() {
        const refreshToken = getRefreshToken();
        clearTokens();
        if (refreshToken) {
            fetch(API_BASE + '/auth/logout', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ refreshToken: refreshToken }),
            }).catch(function () {});
        }
    }

    return { request, login, register, logout, isLoggedIn, getAccessToken, clearTokens };
})();
