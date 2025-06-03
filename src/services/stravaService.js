import axios from 'axios';

const STRAVA_API_URL = 'https://www.strava.com/api/v3';
const CLIENT_ID = '158672';
const CLIENT_SECRET = '42144b0c71224f2530b5d8433d575e0f3775659e';

class StravaService {
  constructor() {
    this.loadTokens();
  }

  loadTokens() {
    this.accessToken = localStorage.getItem('strava_access_token');
    this.refreshToken = localStorage.getItem('strava_refresh_token');
    this.tokenExpiry = localStorage.getItem('strava_token_expiry');
  }

  async exchangeToken(code) {
    try {
      const response = await axios.post('https://www.strava.com/oauth/token', {
        client_id: CLIENT_ID,
        client_secret: CLIENT_SECRET,
        code,
        grant_type: 'authorization_code',
      });

      this.saveTokens(response.data);
      return response.data;
    } catch (error) {
      console.error('Error exchanging token:', error);
      throw error;
    }
  }

  saveTokens(data) {
    localStorage.setItem('strava_access_token', data.access_token);
    localStorage.setItem('strava_refresh_token', data.refresh_token);
    localStorage.setItem('strava_token_expiry', data.expires_at);
    this.accessToken = data.access_token;
    this.refreshToken = data.refresh_token;
    this.tokenExpiry = data.expires_at;
  }

  async refreshAccessToken() {
    try {
      const response = await axios.post('https://www.strava.com/oauth/token', {
        client_id: CLIENT_ID,
        client_secret: CLIENT_SECRET,
        refresh_token: this.refreshToken,
        grant_type: 'refresh_token',
      });

      this.saveTokens(response.data);
      return response.data.access_token;
    } catch (error) {
      console.error('Error refreshing token:', error);
      this.clearTokens();
      throw new Error('No access token available');
    }
  }

  clearTokens() {
    localStorage.removeItem('strava_access_token');
    localStorage.removeItem('strava_refresh_token');
    localStorage.removeItem('strava_token_expiry');
    this.accessToken = null;
    this.refreshToken = null;
    this.tokenExpiry = null;
  }

  async ensureValidToken() {
    if (!this.accessToken || !this.refreshToken) {
      this.clearTokens();
      throw new Error('No access token available');
    }

    const now = Math.floor(Date.now() / 1000);
    if (this.tokenExpiry && now >= this.tokenExpiry - 60) { // Refresh 1 minute before expiry
      await this.refreshAccessToken();
    }
  }

  async makeAuthorizedRequest(endpoint, params = {}) {
    try {
      await this.ensureValidToken();
      const response = await axios.get(`${STRAVA_API_URL}${endpoint}`, {
        headers: { Authorization: `Bearer ${this.accessToken}` },
        params,
      });
      return response.data;
    } catch (error) {
      if (error.response && error.response.status === 401) {
        this.clearTokens();
        throw new Error('No access token available');
      }
      throw error;
    }
  }

  async getAthlete() {
    return this.makeAuthorizedRequest('/athlete');
  }

  async getActivities(page = 1, perPage = 30) {
    return this.makeAuthorizedRequest('/athlete/activities', {
      page,
      per_page: perPage,
    });
  }

  async getStats(athleteId) {
    return this.makeAuthorizedRequest(`/athletes/${athleteId}/stats`);
  }
}

export default new StravaService(); 