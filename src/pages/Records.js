import React, { useEffect, useState } from 'react';
import {
  Grid,
  Paper,
  Typography,
  Box,
  CircularProgress,
  Card,
  CardContent,
  Chip,
  Divider,
  TableContainer,
  Table,
  TableHead,
  TableBody,
  TableRow,
  TableCell,
} from '@mui/material';
import {
  EmojiEvents as TrophyIcon,
  Speed as SpeedIcon,
  Timer as TimerIcon,
  Whatshot as HeatIcon,
  Terrain as TerrainIcon,
} from '@mui/icons-material';
import stravaService from '../services/stravaService';
import { useNavigate } from 'react-router-dom';
import { useSettings } from '../context/SettingsContext';

const RecordCard = ({ title, value, subtitle, icon: Icon }) => (
  <Card sx={{ height: '100%' }}>
    <CardContent>
      <Box sx={{ display: 'flex', alignItems: 'center', mb: 2 }}>
        <Icon sx={{ mr: 1, color: 'primary.main' }} />
        <Typography variant="h6" component="div">
          {title}
        </Typography>
      </Box>
      <Typography variant="h4" component="div" sx={{ mb: 1 }}>
        {value}
      </Typography>
      {subtitle && (
        <Typography variant="body2" color="text.secondary">
          {subtitle}
        </Typography>
      )}
    </CardContent>
  </Card>
);

const COMMON_DISTANCES = [
  { value: 0.1, label: '100m', meters: 100 },
  { value: 0.2, label: '200m', meters: 200 },
  { value: 0.4, label: '400m', meters: 400 },
  { value: 0.8, label: '800m', meters: 800 },
  { value: 1, label: '1K', meters: 1000 },
  { value: 3, label: '3K', meters: 3000 },
  { value: 5, label: '5K', meters: 5000 },
  { value: 10, label: '10K', meters: 10000 },
  { value: 15, label: '15K', meters: 15000 },
  { value: 21.0975, label: 'Half Marathon', meters: 21097.5 },
  { value: 42.195, label: 'Marathon', meters: 42195 },
];

const findBestTimeForDistance = (activities, targetDistance) => {
  const tolerance = 0.02; // 2% tolerance for GPS inaccuracy
  const eligibleActivities = activities.filter(activity => {
    const distanceKm = activity.distance / 1000;
    const lowerBound = targetDistance * (1 - tolerance);
    const upperBound = targetDistance * (1 + tolerance);
    return distanceKm >= lowerBound && distanceKm <= upperBound;
  });

  if (eligibleActivities.length === 0) return null;

  return eligibleActivities.reduce((fastest, activity) => {
    if (!fastest || activity.average_speed > fastest.average_speed) {
      return activity;
    }
    return fastest;
  }, null);
};

const Records = () => {
  const [activities, setActivities] = useState([]);
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();
  const { convertDistance, getDistanceUnit, convertPace, getPaceUnit } = useSettings();

  useEffect(() => {
    const fetchData = async () => {
      try {
        const activitiesData = await stravaService.getActivities(1, 200);
        setActivities(activitiesData.filter(activity => activity.type === 'Run'));
      } catch (error) {
        console.error('Error fetching records:', error);
        if (error.message === 'No access token available') {
          navigate('/login');
        }
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, [navigate]);

  if (loading) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '80vh' }}>
        <CircularProgress />
      </Box>
    );
  }

  // Calculate distance PRs
  const distancePRs = COMMON_DISTANCES.map(distance => {
    const bestActivity = findBestTimeForDistance(activities, distance.value);
    if (!bestActivity) return { ...distance, time: null };
    
    const timeInMinutes = bestActivity.moving_time / 60;
    const pace = convertPace(bestActivity.average_speed);
    const date = new Date(bestActivity.start_date).toLocaleDateString();
    
    return {
      ...distance,
      time: timeInMinutes,
      pace,
      date,
      activity: bestActivity
    };
  });

  // Calculate other records
  const longestRun = activities.reduce((longest, activity) => {
    return (!longest || activity.distance > longest.distance) ? activity : longest;
  }, null);

  const highestElevation = activities.reduce((highest, activity) => {
    return (!highest || activity.total_elevation_gain > highest.total_elevation_gain) ? activity : highest;
  }, null);

  const bestEffort = activities.reduce((best, activity) => {
    const effort = (activity.distance / 1000) * (activity.average_speed);
    if (!best || effort > best.effort) {
      return { ...activity, effort };
    }
    return best;
  }, null);

  // Calculate achievements
  const achievements = [
    activities.length >= 100 ? { name: 'Century Club', description: '100+ activities recorded' } : null,
    activities.some(a => a.distance >= 21097) ? { name: 'Half Marathon', description: 'Completed a half marathon distance' } : null,
    activities.some(a => a.distance >= 42195) ? { name: 'Marathon', description: 'Completed a marathon distance' } : null,
    activities.some(a => a.total_elevation_gain >= 1000) ? { name: 'Mountain Goat', description: '1000m+ elevation in a single run' } : null,
    activities.reduce((total, a) => total + a.distance, 0) >= 1000000 ? { name: '1000K Club', description: 'Total distance over 1000 kilometers' } : null,
    activities.some(a => a.average_speed >= 4.167) ? { name: 'Speed Demon', description: 'Ran faster than 4:00 min/km' } : null,
    activities.some(a => a.moving_time >= 7200) ? { name: 'Endurance Master', description: 'Ran for 2+ hours continuously' } : null,
    activities.filter(a => new Date(a.start_date).getHours() < 6).length >= 10 ? { name: 'Early Bird', description: '10+ runs before 6 AM' } : null,
    activities.some(a => a.average_heartrate && a.average_heartrate < 140 && a.distance >= 5000) ? { name: 'Zone Master', description: '5K+ while keeping HR below 140' } : null,
  ].filter(Boolean);

  // Calculate distance milestones
  const totalDistance = activities.reduce((sum, activity) => sum + activity.distance, 0) / 1000;
  const nextMilestone = Math.ceil(totalDistance / 100) * 100;
  const progressToNextMilestone = ((totalDistance % 100) / 100) * 100;

  return (
    <Box sx={{ flexGrow: 1, mt: 4 }}>
      <Typography variant="h4" gutterBottom>
        Personal Records
      </Typography>

      <Grid container spacing={3}>
        {/* Distance PRs */}
        <Grid item xs={12}>
          <Paper sx={{ p: 3 }}>
            <Typography variant="h6" gutterBottom sx={{ display: 'flex', alignItems: 'center' }}>
              <TrophyIcon sx={{ mr: 1, color: 'primary.main' }} />
              Distance Records
            </Typography>
            <TableContainer>
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>Distance</TableCell>
                    <TableCell align="right">Time</TableCell>
                    <TableCell align="right">Pace</TableCell>
                    <TableCell>Date</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {distancePRs.map((pr) => (
                    <TableRow key={pr.label} hover>
                      <TableCell>{pr.label}</TableCell>
                      <TableCell align="right">
                        {pr.time ? (
                          `${Math.floor(pr.time)}:${String(Math.round((pr.time % 1) * 60)).padStart(2, '0')}`
                        ) : (
                          '—'
                        )}
                      </TableCell>
                      <TableCell align="right">
                        {pr.pace ? `${pr.pace} ${getPaceUnit()}` : '—'}
                      </TableCell>
                      <TableCell>{pr.date || '—'}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          </Paper>
        </Grid>

        {/* Other Records */}
        <Grid item xs={12} md={4}>
          <RecordCard
            title="Longest Run"
            value={`${convertDistance(longestRun.distance / 1000)} ${getDistanceUnit()}`}
            subtitle={`on ${new Date(longestRun.start_date).toLocaleDateString()}`}
            icon={TimerIcon}
          />
        </Grid>
        <Grid item xs={12} md={4}>
          <RecordCard
            title="Highest Elevation"
            value={`${Math.round(highestElevation.total_elevation_gain)}m`}
            subtitle={`${convertDistance(highestElevation.distance / 1000)} ${getDistanceUnit()} on ${new Date(highestElevation.start_date).toLocaleDateString()}`}
            icon={TerrainIcon}
          />
        </Grid>
        <Grid item xs={12} md={4}>
          <RecordCard
            title="Best Overall Effort"
            value={convertPace(bestEffort.average_speed)}
            subtitle={`${convertDistance(bestEffort.distance / 1000)} ${getDistanceUnit()} on ${new Date(bestEffort.start_date).toLocaleDateString()}`}
            icon={HeatIcon}
          />
        </Grid>

        {/* Achievements Section */}
        <Grid item xs={12}>
          <Paper sx={{ p: 3, mt: 2 }}>
            <Typography variant="h6" gutterBottom sx={{ display: 'flex', alignItems: 'center' }}>
              <TrophyIcon sx={{ mr: 1, color: 'primary.main' }} />
              Achievements Unlocked
            </Typography>
            <Divider sx={{ my: 2 }} />
            <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1 }}>
              {achievements.map((achievement, index) => (
                <Chip
                  key={index}
                  label={achievement.name}
                  color="primary"
                  variant="outlined"
                  title={achievement.description}
                  sx={{ m: 0.5 }}
                />
              ))}
            </Box>
          </Paper>
        </Grid>

        {/* Progress to Next Milestone */}
        <Grid item xs={12}>
          <Paper sx={{ p: 3, mt: 2 }}>
            <Typography variant="h6" gutterBottom>
              Distance Milestone Progress
            </Typography>
            <Box sx={{ position: 'relative', pt: 2 }}>
              <Box
                sx={{
                  width: '100%',
                  height: 10,
                  backgroundColor: 'grey.200',
                  borderRadius: 5,
                }}
              >
                <Box
                  sx={{
                    width: `${progressToNextMilestone}%`,
                    height: '100%',
                    backgroundColor: 'primary.main',
                    borderRadius: 5,
                    transition: 'width 0.5s ease-in-out',
                  }}
                />
              </Box>
              <Typography variant="body2" sx={{ mt: 1 }}>
                {convertDistance(totalDistance)} {getDistanceUnit()} / {convertDistance(nextMilestone)} {getDistanceUnit()}
              </Typography>
            </Box>
          </Paper>
        </Grid>
      </Grid>
    </Box>
  );
};

export default Records; 