import axios from 'axios';

const CLIENT_ID = process.env.REACT_APP_STRAVA_CLIENT_ID || '158672';
const CLIENT_SECRET = process.env.REACT_APP_STRAVA_CLIENT_SECRET || '42144b0c71224f2530b5d8433d575e0f3775659e';

const refreshToken = async () => {
  const refresh_token = localStorage.getItem('strava_refresh_token');
  if (!refresh_token) return null;

  try {
    const response = await axios.post('https://www.strava.com/oauth/token', {
      client_id: CLIENT_ID,
      client_secret: CLIENT_SECRET,
      refresh_token,
      grant_type: 'refresh_token'
    });

    localStorage.setItem('strava_access_token', response.data.access_token);
    localStorage.setItem('strava_refresh_token', response.data.refresh_token);
    localStorage.setItem('strava_token_expiry', response.data.expires_at);
    
    return response.data.access_token;
  } catch (error) {
    console.error('Error refreshing token:', error);
    return null;
  }
};

const getValidToken = async () => {
  const access_token = localStorage.getItem('strava_access_token');
  const expiry = localStorage.getItem('strava_token_expiry');
  
  if (!access_token || !expiry) return null;
  
  const now = Math.floor(Date.now() / 1000);
  if (now >= expiry - 60) {
    return refreshToken();
  }
  
  return access_token;
};

export const getActivities = async (page = 1, per_page = 30) => {
  const token = await getValidToken();
  if (!token) {
    window.location.href = '/login';
    return [];
  }

  try {
    const response = await axios.get('https://www.strava.com/api/v3/athlete/activities', {
      headers: { Authorization: `Bearer ${token}` },
      params: { page, per_page }
    });
    return response.data;
  } catch (error) {
    console.error('Error fetching activities:', error);
    if (error.response?.status === 401) {
      window.location.href = '/login';
    }
    return [];
  }
};

export const getAthlete = async () => {
  const token = await getValidToken();
  if (!token) {
    window.location.href = '/login';
    return null;
  }

  try {
    const response = await axios.get('https://www.strava.com/api/v3/athlete', {
      headers: { Authorization: `Bearer ${token}` }
    });
    return response.data;
  } catch (error) {
    console.error('Error fetching athlete:', error);
    if (error.response?.status === 401) {
      window.location.href = '/login';
    }
    return null;
  }
};

export const getStats = async (athleteId) => {
  const token = await getValidToken();
  if (!token) {
    window.location.href = '/login';
    return null;
  }

  try {
    const response = await axios.get(`https://www.strava.com/api/v3/athletes/${athleteId}/stats`, {
      headers: { Authorization: `Bearer ${token}` }
    });
    return response.data;
  } catch (error) {
    console.error('Error fetching stats:', error);
    if (error.response?.status === 401) {
      window.location.href = '/login';
    }
    return null;
  }
}; 