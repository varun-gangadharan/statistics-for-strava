import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';
import {
  Box,
  Button,
  Typography,
  Paper,
  CircularProgress,
} from '@mui/material';
import { DirectionsRun } from '@mui/icons-material';

const CLIENT_ID = process.env.REACT_APP_STRAVA_CLIENT_ID || '158672';
const CLIENT_SECRET = process.env.REACT_APP_STRAVA_CLIENT_SECRET || '42144b0c71224f2530b5d8433d575e0f3775659e';
const REDIRECT_URI = `${window.location.origin}/callback`;

const Login = () => {
  const navigate = useNavigate();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    const handleCallback = async () => {
      const urlParams = new URLSearchParams(window.location.search);
      const code = urlParams.get('code');
      
      if (code) {
        setLoading(true);
        try {
          const response = await axios.post('https://www.strava.com/oauth/token', {
            client_id: CLIENT_ID,
            client_secret: CLIENT_SECRET,
            code,
            grant_type: 'authorization_code'
          });

          // Store everything in localStorage
          localStorage.setItem('strava_access_token', response.data.access_token);
          localStorage.setItem('strava_refresh_token', response.data.refresh_token);
          localStorage.setItem('strava_token_expiry', response.data.expires_at);
          localStorage.setItem('strava_athlete', JSON.stringify(response.data.athlete));

          navigate('/');
        } catch (err) {
          console.error('Authentication error:', err);
          setError('Failed to authenticate with Strava');
        } finally {
          setLoading(false);
        }
      }
    };

    handleCallback();
  }, [navigate]);

  const handleLogin = () => {
    const scope = 'read,activity:read_all,profile:read_all';
    const params = new URLSearchParams({
      client_id: CLIENT_ID,
      redirect_uri: REDIRECT_URI,
      response_type: 'code',
      scope: scope,
      approval_prompt: 'force'
    });
    window.location.href = `https://www.strava.com/oauth/authorize?${params.toString()}`;
  };

  if (loading) {
    return (
      <Box
        sx={{
          display: 'flex',
          justifyContent: 'center',
          alignItems: 'center',
          minHeight: '100vh',
        }}
      >
        <CircularProgress />
      </Box>
    );
  }

  return (
    <Box
      sx={{
        display: 'flex',
        justifyContent: 'center',
        alignItems: 'center',
        minHeight: '100vh',
        bgcolor: '#f5f5f5',
      }}
    >
      <Paper
        elevation={3}
        sx={{
          p: 4,
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
          maxWidth: 400,
          width: '90%',
        }}
      >
        <DirectionsRun sx={{ fontSize: 48, color: '#FC4C02', mb: 2 }} />
        <Typography variant="h4" component="h1" gutterBottom>
          Statistics for Strava
        </Typography>
        <Typography variant="body1" color="textSecondary" align="center" sx={{ mb: 3 }}>
          Connect with Strava to analyze your activities and get personalized insights
        </Typography>
        {error && (
          <Typography color="error" sx={{ mb: 2 }}>
            {error}
          </Typography>
        )}
        <Button
          variant="contained"
          onClick={handleLogin}
          sx={{
            bgcolor: '#FC4C02',
            '&:hover': {
              bgcolor: '#E34902',
            },
          }}
        >
          Connect with Strava
        </Button>
      </Paper>
    </Box>
  );
};

export default Login; 