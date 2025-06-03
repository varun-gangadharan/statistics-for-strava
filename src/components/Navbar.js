import React from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import {
  AppBar,
  Toolbar,
  Typography,
  Box,
  IconButton,
  Button,
  Tooltip,
} from '@mui/material';
import {
  Dashboard as DashboardIcon,
  DirectionsRun,
  DirectionsRun as ActivityIcon,
  Person as ProfileIcon,
  Timeline as AnalyticsIcon,
  EmojiEvents as RecordsIcon,
  Speed as SpeedIcon,
  FitnessCenter as TrainingIcon,
} from '@mui/icons-material';
import { useSettings } from '../context/SettingsContext';

const Navbar = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const { useMetric, toggleUnit, getDistanceUnit } = useSettings();

  const isActive = (path) => location.pathname === path;

  return (
    <AppBar position="static">
      <Toolbar>
        <Typography
          variant="h6"
          component="div"
          sx={{
            flexGrow: 1,
            fontWeight: 'bold',
            cursor: 'pointer',
            display: 'flex',
            alignItems: 'center',
          }}
          onClick={() => navigate('/')}
        >
          <DirectionsRun sx={{ mr: 1 }} />
          RUNMAN
        </Typography>
        <Box sx={{ display: 'flex', gap: 2, alignItems: 'center' }}>
          <Tooltip title="Dashboard">
            <IconButton
              color={isActive('/') ? 'secondary' : 'inherit'}
              onClick={() => navigate('/')}
            >
              <DashboardIcon />
            </IconButton>
          </Tooltip>
          <Tooltip title="Activities">
            <IconButton
              color={isActive('/activities') ? 'secondary' : 'inherit'}
              onClick={() => navigate('/activities')}
            >
              <ActivityIcon />
            </IconButton>
          </Tooltip>
          <Tooltip title="Analytics">
            <IconButton
              color={isActive('/analytics') ? 'secondary' : 'inherit'}
              onClick={() => navigate('/analytics')}
            >
              <AnalyticsIcon />
            </IconButton>
          </Tooltip>
          <Tooltip title="Records">
            <IconButton
              color={isActive('/records') ? 'secondary' : 'inherit'}
              onClick={() => navigate('/records')}
            >
              <RecordsIcon />
            </IconButton>
          </Tooltip>
          <Tooltip title="Training Plan">
            <IconButton
              color={isActive('/training') ? 'secondary' : 'inherit'}
              onClick={() => navigate('/training')}
            >
              <TrainingIcon />
            </IconButton>
          </Tooltip>
          <Tooltip title="Profile">
            <IconButton
              color={isActive('/profile') ? 'secondary' : 'inherit'}
              onClick={() => navigate('/profile')}
            >
              <ProfileIcon />
            </IconButton>
          </Tooltip>
          <Tooltip title="Toggle units">
            <Button
              variant="outlined"
              color="inherit"
              startIcon={<SpeedIcon />}
              onClick={toggleUnit}
              size="small"
              sx={{ ml: 2 }}
            >
              {getDistanceUnit().toUpperCase()}
            </Button>
          </Tooltip>
        </Box>
      </Toolbar>
    </AppBar>
  );
};

export default Navbar; 