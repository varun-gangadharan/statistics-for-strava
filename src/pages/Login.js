import React, { useEffect } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import {
  Box,
  Button,
  Typography,
  Paper,
  CircularProgress,
} from '@mui/material';
import { DirectionsRun } from '@mui/icons-material';
import stravaService from '../services/stravaService';

const CLIENT_ID = '158672';
const REDIRECT_URI = `${window.location.origin}/login`;

const Login = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const [loading, setLoading] = React.useState(false);
  const [error, setError] = React.useState(null);

  useEffect(() => {
    const handleAuthCallback = async () => {
      const urlParams = new URLSearchParams(location.search);
      const code = urlParams.get('code');
      
      if (code) {
        setLoading(true);
        try {
          await stravaService.exchangeToken(code);
          navigate('/');
        } catch (err) {
          console.error('Authentication error:', err);
          setError('Failed to authenticate with Strava. Please try again.');
        } finally {
          setLoading(false);
        }
      }
    };

    handleAuthCallback();
  }, [location, navigate]);

  const handleLogin = () => {
    const scope = 'read,activity:read_all,profile:read_all';
    window.location.href = `https://www.strava.com/oauth/authorize?client_id=${CLIENT_ID}&redirect_uri=${REDIRECT_URI}&response_type=code&scope=${scope}`;
  };

  if (loading) {
    return (
      <Box
        sx={{
          display: 'flex',
          justifyContent: 'center',
          alignItems: 'center',
          minHeight: '80vh',
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
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        minHeight: '80vh',
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
          width: '100%',
        }}
      >
        <DirectionsRun sx={{ fontSize: 60, mb: 2, color: 'primary.main' }} />
        <Typography variant="h4" component="h1" gutterBottom>
          Welcome to Runman
        </Typography>
        <Typography variant="body1" color="text.secondary" align="center" sx={{ mb: 3 }}>
          Connect with Strava to track your running statistics and analyze your performance
        </Typography>
        {error && (
          <Typography color="error" sx={{ mb: 2 }}>
            {error}
          </Typography>
        )}
        <Button
          variant="contained"
          color="primary"
          size="large"
          onClick={handleLogin}
          sx={{
            backgroundColor: '#FC4C02',
            '&:hover': {
              backgroundColor: '#E34402',
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