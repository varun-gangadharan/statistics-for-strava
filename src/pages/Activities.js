import React, { useEffect, useState } from 'react';
import {
  Box,
  Typography,
  Paper,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Chip,
  CircularProgress,
} from '@mui/material';
import { DirectionsRun, DirectionsBike, Pool } from '@mui/icons-material';
import stravaService from '../services/stravaService';
import { useNavigate } from 'react-router-dom';
import { useSettings } from '../context/SettingsContext';

const getActivityIcon = (type) => {
  switch (type) {
    case 'Run':
      return <DirectionsRun />;
    case 'Ride':
      return <DirectionsBike />;
    case 'Swim':
      return <Pool />;
    default:
      return <DirectionsRun />;
  }
};

const formatDuration = (seconds) => {
  const hours = Math.floor(seconds / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  const remainingSeconds = seconds % 60;
  
  if (hours > 0) {
    return `${hours}:${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
  }
  return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
};

const Activities = () => {
  const [activities, setActivities] = useState([]);
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();
  const { convertDistance, getDistanceUnit, convertElevation, getElevationUnit, convertPace, getPaceUnit } = useSettings();

  useEffect(() => {
    const fetchActivities = async () => {
      try {
        const data = await stravaService.getActivities(1, 20);
        setActivities(data);
      } catch (error) {
        console.error('Error fetching activities:', error);
        if (error.message === 'No access token available') {
          navigate('/login');
        }
      } finally {
        setLoading(false);
      }
    };

    fetchActivities();
  }, [navigate]);

  if (loading) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '80vh' }}>
        <CircularProgress />
      </Box>
    );
  }

  return (
    <Box sx={{ flexGrow: 1, mt: 4 }}>
      <Typography variant="h4" gutterBottom>
        Recent Activities
      </Typography>

      <TableContainer component={Paper}>
        <Table sx={{ minWidth: 650 }}>
          <TableHead>
            <TableRow>
              <TableCell>Type</TableCell>
              <TableCell>Name</TableCell>
              <TableCell>Date</TableCell>
              <TableCell align="right">Distance ({getDistanceUnit()})</TableCell>
              <TableCell align="right">Time</TableCell>
              <TableCell align="right">Pace {getPaceUnit()}</TableCell>
              <TableCell align="right">Elevation ({getElevationUnit()})</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {activities.map((activity) => (
              <TableRow
                key={activity.id}
                sx={{ '&:last-child td, &:last-child th': { border: 0 } }}
              >
                <TableCell>
                  <Chip
                    icon={getActivityIcon(activity.type)}
                    label={activity.type}
                    color={activity.type === 'Run' ? 'primary' : 'secondary'}
                    size="small"
                  />
                </TableCell>
                <TableCell component="th" scope="row">
                  {activity.name}
                </TableCell>
                <TableCell>
                  {new Date(activity.start_date_local).toLocaleDateString()}
                </TableCell>
                <TableCell align="right">
                  {convertDistance(activity.distance / 1000)}
                </TableCell>
                <TableCell align="right">
                  {formatDuration(activity.moving_time)}
                </TableCell>
                <TableCell align="right">
                  {activity.type === 'Ride' 
                    ? `${(activity.average_speed * 3.6).toFixed(1)} km/h`
                    : convertPace(activity.average_speed)}
                </TableCell>
                <TableCell align="right">
                  {convertElevation(activity.total_elevation_gain)}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </TableContainer>
    </Box>
  );
};

export default Activities; 