import React, { useEffect, useState } from 'react';
import {
  Grid,
  Paper,
  Typography,
  Box,
  CircularProgress,
  TextField,
  Button,
  Slider,
  Select,
  MenuItem,
  FormControl,
  InputLabel,
  Alert,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Chip,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  IconButton,
} from '@mui/material';
import {
  CalendarMonth as CalendarIcon,
  DirectionsRun as RunIcon,
  Speed as SpeedIcon,
  Warning as WarningIcon,
  Close as CloseIcon,
  Download as DownloadIcon,
} from '@mui/icons-material';
import stravaService from '../services/stravaService';
import { useNavigate } from 'react-router-dom';
import { useSettings } from '../context/SettingsContext';

const MIN_ACTIVITIES_REQUIRED = 20;
const WEEKS_MIN = 8;
const WEEKS_MAX = 24;

const COMMON_DISTANCES = [
  { value: 0.1, label: '100m' },
  { value: 0.2, label: '200m' },
  { value: 0.4, label: '400m' },
  { value: 0.8, label: '800m' },
  { value: 1, label: '1K' },
  { value: 3, label: '3K' },
  { value: 5, label: '5K' },
  { value: 10, label: '10K' },
  { value: 15, label: '15K' },
  { value: 21.0975, label: 'Half Marathon' },
  { value: 42.195, label: 'Marathon' },
];

const WORKOUT_TYPES = {
  easy: {
    description: 'Easy/Recovery Run',
    paceAdjustment: 1.2, // 20% slower than base pace
    color: '#4CAF50',
  },
  long: {
    description: 'Long Run',
    paceAdjustment: 1.15, // 15% slower than base pace
    color: '#2196F3',
  },
  tempo: {
    description: 'Tempo Run',
    paceAdjustment: 0.9, // 10% faster than base pace
    color: '#FF9800',
  },
  speed: {
    description: 'Speed Work',
    paceAdjustment: 0.85, // 15% faster than base pace
    color: '#F44336',
  },
};

const calculateTrainingLoad = (activities) => {
  // Calculate training load using relative effort and decay over time
  return activities.reduce((load, activity) => {
    const daysAgo = (new Date() - new Date(activity.start_date)) / (1000 * 60 * 60 * 24);
    const decayFactor = Math.exp(-daysAgo / 7); // 7-day half-life
    const relativeEffort = (activity.distance / 1000) * 
      (activity.average_heartrate ? (activity.average_heartrate / 150) : 1);
    return load + (relativeEffort * decayFactor);
  }, 0);
};

const predictRaceTime = (activities, targetDistanceKm, goal) => {
  // Get recent activities within last 6 months
  const sixMonthsAgo = new Date();
  sixMonthsAgo.setMonth(sixMonthsAgo.getMonth() - 6);
  
  const recentActivities = activities.filter(a => 
    new Date(a.start_date) >= sixMonthsAgo
  );

  if (recentActivities.length === 0) return null;

  // Find best performances at various distances
  const performances = recentActivities.map(activity => ({
    distance: activity.distance / 1000, // km
    time: activity.moving_time / 60, // minutes
    pace: (activity.moving_time / 60) / (activity.distance / 1000) // min/km
  }));

  // Use Riegel formula with modifications based on goal
  const bestPerformance = performances.reduce((best, current) => {
    if (current.distance >= targetDistanceKm * 0.3 && // Only consider runs at least 30% of target
        (!best || current.pace < best.pace)) {
      return current;
    }
    return best;
  }, null);

  if (!bestPerformance) return null;

  // Riegel formula: T2 = T1 * (D2/D1)^1.06
  let predictedTime = bestPerformance.time * 
    Math.pow(targetDistanceKm / bestPerformance.distance, 1.06);

  // Adjust based on goal
  switch (goal) {
    case 'pr':
      predictedTime *= 0.95; // 5% faster
      break;
    case 'compete':
      predictedTime *= 0.9; // 10% faster
      break;
    default: // 'finish'
      predictedTime *= 1.05; // 5% slower for comfortable finish
  }

  return Math.round(predictedTime);
};

const TrainingPlan = () => {
  const [activities, setActivities] = useState([]);
  const [loading, setLoading] = useState(true);
  const [plan, setPlan] = useState(null);
  const navigate = useNavigate();
  const { convertDistance, getDistanceUnit, convertPace, getPaceUnit } = useSettings();

  // Form state
  const [targetDistance, setTargetDistance] = useState(5);
  const [targetDate, setTargetDate] = useState('');
  const [daysPerWeek, setDaysPerWeek] = useState(4);
  const [peakMileage, setPeakMileage] = useState('');
  const [goal, setGoal] = useState('finish'); // 'finish', 'pr', 'compete'
  const [error, setError] = useState(null);
  const [selectedWeek, setSelectedWeek] = useState(null);
  const [weeklyDetails, setWeeklyDetails] = useState(null);
  const [trainingLoad, setTrainingLoad] = useState(0);
  const [predictedTime, setPredictedTime] = useState(null);

  useEffect(() => {
    const fetchData = async () => {
      try {
        const activitiesData = await stravaService.getActivities(1, 200);
        const runningActivities = activitiesData.filter(activity => activity.type === 'Run');
        setActivities(runningActivities);
        setTrainingLoad(calculateTrainingLoad(runningActivities));
        
        if (runningActivities.length < MIN_ACTIVITIES_REQUIRED) {
          setError(`Need at least ${MIN_ACTIVITIES_REQUIRED} activities to generate a training plan. You have ${runningActivities.length}.`);
        }
      } catch (error) {
        console.error('Error fetching activities:', error);
        if (error.message === 'No access token available') {
          navigate('/login');
        }
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, [navigate]);

  useEffect(() => {
    if (targetDistance && activities.length > 0 && goal) {
      const predicted = predictRaceTime(activities, targetDistance, goal);
      setPredictedTime(predicted);
    }
  }, [targetDistance, activities, goal]);

  const analyzeUserData = () => {
    // Calculate user's current fitness level
    const recentActivities = activities.slice(0, 30);
    const avgWeeklyDistance = recentActivities.reduce((sum, activity) => 
      sum + activity.distance, 0) / (1000 * 4); // Last 4 weeks
    
    const maxDistance = Math.max(...activities.map(a => a.distance));
    const avgPace = activities.reduce((sum, a) => sum + a.average_speed, 0) / activities.length;
    const consistentPace = activities.reduce((sum, a) => 
      sum + Math.abs(a.average_speed - avgPace), 0) / activities.length;

    return {
      currentWeeklyDistance: avgWeeklyDistance,
      longestRun: maxDistance,
      averagePace: avgPace,
      paceConsistency: consistentPace
    };
  };

  const generatePlan = () => {
    if (activities.length < MIN_ACTIVITIES_REQUIRED) {
      setError(`Need at least ${MIN_ACTIVITIES_REQUIRED} activities to generate a training plan.`);
      return;
    }

    const targetDateObj = new Date(targetDate);
    const today = new Date();
    const weeksUntilRace = Math.ceil((targetDateObj - today) / (7 * 24 * 60 * 60 * 1000));

    if (weeksUntilRace < WEEKS_MIN || weeksUntilRace > WEEKS_MAX) {
      setError(`Race date should be between ${WEEKS_MIN} and ${WEEKS_MAX} weeks from now.`);
      return;
    }

    const userMetrics = analyzeUserData();
    const targetDistanceKm = parseInt(targetDistance);
    
    // If no peak mileage is set, calculate a safe default based on current fitness
    const safePeakMileage = !peakMileage ? Math.round(userMetrics.currentWeeklyDistance * 1.3) : parseFloat(peakMileage);
    
    // Generate weekly targets with injury prevention in mind
    const weeks = [];
    const buildupWeeks = Math.floor(weeksUntilRace * 0.7);
    const peakWeeks = Math.floor(weeksUntilRace * 0.2);
    const taperWeeks = weeksUntilRace - buildupWeeks - peakWeeks;

    let currentWeeklyDistance = userMetrics.currentWeeklyDistance;
    
    // Limit weekly increase to 10% for injury prevention
    const maxSafeIncrease = Math.min(
      (safePeakMileage - currentWeeklyDistance) / buildupWeeks,
      currentWeeklyDistance * 0.1
    );

    // Build-up phase
    for (let i = 0; i < buildupWeeks; i++) {
      currentWeeklyDistance += maxSafeIncrease;
      weeks.push(generateWeek(currentWeeklyDistance, daysPerWeek, 'build', userMetrics));
    }

    // Peak phase
    for (let i = 0; i < peakWeeks; i++) {
      weeks.push(generateWeek(safePeakMileage, daysPerWeek, 'peak', userMetrics));
    }

    // Taper phase
    for (let i = 0; i < taperWeeks; i++) {
      const taperDistance = safePeakMileage * (1 - ((i + 1) / taperWeeks) * 0.4);
      weeks.push(generateWeek(taperDistance, daysPerWeek, 'taper', userMetrics));
    }

    setPlan({
      targetDate: targetDateObj,
      weeks,
      metrics: userMetrics,
    });
  };

  const generateWeek = (weeklyDistance, daysPerWeek, phase, metrics) => {
    const runs = [];
    const distancePerDay = weeklyDistance / daysPerWeek;
    
    // Distribute weekly distance across days
    for (let i = 0; i < 7; i++) {
      if (i < daysPerWeek) {
        let distance = distancePerDay;
        let type = 'easy';
        let intensity = 'low';

        // Adjust based on phase and day of week
        if (phase === 'build' || phase === 'peak') {
          if (i === 0) { // Long run
            distance *= 1.5;
            type = 'long';
          } else if (i === 2 && goal !== 'finish') { // Speed work
            distance *= 0.8;
            type = 'speed';
            intensity = 'high';
          } else if (i === 4 && goal === 'compete') { // Tempo
            distance *= 0.9;
            type = 'tempo';
            intensity = 'medium';
          }
        }

        runs.push({
          day: i,
          distance: Math.round(distance * 10) / 10,
          type,
          intensity,
        });
      }
    }

    return {
      totalDistance: weeklyDistance,
      runs,
      phase,
    };
  };

  const generateWorkoutDetails = (run, metrics) => {
    const { type, distance, intensity } = run;
    const basePace = metrics.averagePace;
    const workoutType = WORKOUT_TYPES[type];
    const targetPace = basePace * workoutType.paceAdjustment;

    let details = '';
    switch (type) {
      case 'easy':
        details = `Easy-paced run focusing on recovery and building aerobic base.`;
        break;
      case 'long':
        details = `Long run at conversational pace. Focus on time on feet and endurance building.`;
        break;
      case 'tempo':
        details = `Warm up 2km\nMain set: ${(distance * 0.7).toFixed(1)}km at tempo pace\nCool down 2km`;
        break;
      case 'speed':
        const intervalDistance = 400; // meters
        const sets = Math.floor((distance * 1000 * 0.6) / intervalDistance);
        details = `Warm up 2km\n${sets}x400m at 5K race pace\n200m easy jog recovery between intervals\nCool down 2km`;
        break;
      default:
        details = 'Easy-paced run';
    }

    return {
      ...run,
      details,
      targetPace: convertPace(targetPace),
    };
  };

  const handleWeekClick = (weekIndex) => {
    const week = plan.weeks[weekIndex];
    const detailedRuns = week.runs.map(run => 
      generateWorkoutDetails(run, plan.metrics)
    );
    setWeeklyDetails({ ...week, runs: detailedRuns, weekNumber: weekIndex + 1 });
    setSelectedWeek(weekIndex);
  };

  const handleCloseWeeklyDetails = () => {
    setSelectedWeek(null);
    setWeeklyDetails(null);
  };

  const exportToCsv = () => {
    if (!plan) return;

    const rows = [
      ['Week', 'Day', 'Type', 'Distance', 'Details', 'Target Pace'],
    ];

    plan.weeks.forEach((week, weekIndex) => {
      const detailedRuns = week.runs.map(run => 
        generateWorkoutDetails(run, plan.metrics)
      );

      detailedRuns.forEach(run => {
        rows.push([
          weekIndex + 1,
          ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][run.day],
          WORKOUT_TYPES[run.type].description,
          `${convertDistance(run.distance)} ${getDistanceUnit()}`,
          run.details.replace(/\n/g, ' '),
          run.targetPace + getPaceUnit(),
        ]);
      });
    });

    const csvContent = rows.map(row => row.join(',')).join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.setAttribute('hidden', '');
    a.setAttribute('href', url);
    a.setAttribute('download', 'training-plan.csv');
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  };

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
        Smart Training Plan Generator
        <Chip
          label="Data-Driven"
          color="primary"
          size="small"
          sx={{ ml: 2 }}
        />
      </Typography>

      {error && (
        <Alert severity="warning" sx={{ mb: 3 }}>
          {error}
        </Alert>
      )}

      <Grid container spacing={3}>
        <Grid item xs={12} md={6}>
          <Paper sx={{ p: 3 }}>
            <Typography variant="h6" gutterBottom>
              Training Analysis & Plan Configuration
            </Typography>
            <Box sx={{ mb: 3 }}>
              <Typography variant="body2" color="text.secondary" gutterBottom>
                Using exponential decay algorithm for training load and Riegel formula for predictions
              </Typography>
              <Typography variant="body1" gutterBottom>
                Current Training Load: {trainingLoad.toFixed(1)}
              </Typography>
              {predictedTime && (
                <Typography variant="body1" gutterBottom>
                  Statistical Race Prediction: {Math.floor(predictedTime / 60)}h {Math.round(predictedTime % 60)}m
                </Typography>
              )}
            </Box>
            <Grid container spacing={2}>
              <Grid item xs={12}>
                <FormControl fullWidth>
                  <InputLabel>Target Distance</InputLabel>
                  <Select
                    value={targetDistance}
                    onChange={(e) => setTargetDistance(e.target.value)}
                    disabled={activities.length < MIN_ACTIVITIES_REQUIRED}
                  >
                    {COMMON_DISTANCES.map(({ value, label }) => (
                      <MenuItem key={value} value={value}>
                        {label}
                      </MenuItem>
                    ))}
                  </Select>
                </FormControl>
              </Grid>
              <Grid item xs={12}>
                <TextField
                  fullWidth
                  label="Race Date"
                  type="date"
                  value={targetDate}
                  onChange={(e) => setTargetDate(e.target.value)}
                  InputLabelProps={{ shrink: true }}
                  disabled={activities.length < MIN_ACTIVITIES_REQUIRED}
                />
              </Grid>
              <Grid item xs={12}>
                <FormControl fullWidth>
                  <InputLabel>Training Days per Week</InputLabel>
                  <Select
                    value={daysPerWeek}
                    onChange={(e) => setDaysPerWeek(e.target.value)}
                    disabled={activities.length < MIN_ACTIVITIES_REQUIRED}
                  >
                    {[3, 4, 5, 6].map(days => (
                      <MenuItem key={days} value={days}>{days} days</MenuItem>
                    ))}
                  </Select>
                </FormControl>
              </Grid>
              <Grid item xs={12}>
                <TextField
                  fullWidth
                  label={`Peak Weekly Distance (${getDistanceUnit()}) - Optional`}
                  type="number"
                  value={peakMileage}
                  onChange={(e) => setPeakMileage(e.target.value)}
                  disabled={activities.length < MIN_ACTIVITIES_REQUIRED}
                  helperText="Leave blank for AI to suggest a safe peak distance based on your current fitness"
                />
              </Grid>
              <Grid item xs={12}>
                <FormControl fullWidth>
                  <InputLabel>Goal</InputLabel>
                  <Select
                    value={goal}
                    onChange={(e) => setGoal(e.target.value)}
                    disabled={activities.length < MIN_ACTIVITIES_REQUIRED}
                  >
                    <MenuItem value="finish">Finish Comfortably</MenuItem>
                    <MenuItem value="pr">Set a PR</MenuItem>
                    <MenuItem value="compete">Compete</MenuItem>
                  </Select>
                </FormControl>
              </Grid>
              <Grid item xs={12}>
                <Button
                  fullWidth
                  variant="contained"
                  onClick={generatePlan}
                  disabled={activities.length < MIN_ACTIVITIES_REQUIRED}
                >
                  Generate Plan
                </Button>
              </Grid>
            </Grid>
          </Paper>
        </Grid>

        {plan && (
          <Grid item xs={12} md={6}>
            <Paper sx={{ p: 3 }}>
              <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
                <Typography variant="h6">
                  Generated Training Plan
                </Typography>
                <Button
                  startIcon={<DownloadIcon />}
                  onClick={exportToCsv}
                  variant="outlined"
                  size="small"
                >
                  Export Plan
                </Button>
              </Box>
              
              <Box sx={{ mb: 3 }}>
                <Typography variant="subtitle2" color="text.secondary" gutterBottom>
                  Race Day: {plan.targetDate.toLocaleDateString()}
                </Typography>
              </Box>

              <TableContainer>
                <Table size="small">
                  <TableHead>
                    <TableRow>
                      <TableCell>Week</TableCell>
                      <TableCell>Phase</TableCell>
                      <TableCell align="right">Distance</TableCell>
                      <TableCell>Key Workouts</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {plan.weeks.map((week, index) => (
                      <TableRow 
                        key={index}
                        onClick={() => handleWeekClick(index)}
                        sx={{ 
                          cursor: 'pointer',
                          '&:hover': { backgroundColor: 'action.hover' },
                        }}
                      >
                        <TableCell>{index + 1}</TableCell>
                        <TableCell>
                          <Chip
                            label={week.phase}
                            size="small"
                            color={
                              week.phase === 'build' ? 'primary' :
                              week.phase === 'peak' ? 'secondary' : 'default'
                            }
                          />
                        </TableCell>
                        <TableCell align="right">
                          {convertDistance(week.totalDistance)} {getDistanceUnit()}
                        </TableCell>
                        <TableCell>
                          {week.runs
                            .filter(run => run.type !== 'easy')
                            .map((run, i) => (
                              <Chip
                                key={i}
                                label={run.type}
                                size="small"
                                variant="outlined"
                                sx={{ 
                                  mr: 0.5,
                                  borderColor: WORKOUT_TYPES[run.type].color,
                                  color: WORKOUT_TYPES[run.type].color,
                                }}
                              />
                            ))}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </TableContainer>
            </Paper>
          </Grid>
        )}
      </Grid>

      {/* Weekly Details Dialog */}
      <Dialog 
        open={Boolean(selectedWeek)} 
        onClose={handleCloseWeeklyDetails}
        maxWidth="md"
        fullWidth
      >
        {weeklyDetails && (
          <>
            <DialogTitle>
              <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <Typography variant="h6">
                  Week {weeklyDetails.weekNumber} - {weeklyDetails.phase.charAt(0).toUpperCase() + weeklyDetails.phase.slice(1)} Phase
                </Typography>
                <IconButton onClick={handleCloseWeeklyDetails} size="small">
                  <CloseIcon />
                </IconButton>
              </Box>
            </DialogTitle>
            <DialogContent>
              <TableContainer>
                <Table>
                  <TableHead>
                    <TableRow>
                      <TableCell>Day</TableCell>
                      <TableCell>Type</TableCell>
                      <TableCell align="right">Distance</TableCell>
                      <TableCell>Target Pace</TableCell>
                      <TableCell>Workout Details</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {weeklyDetails.runs.map((run, index) => (
                      <TableRow key={index}>
                        <TableCell>
                          {['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][run.day]}
                        </TableCell>
                        <TableCell>
                          <Chip
                            label={WORKOUT_TYPES[run.type].description}
                            size="small"
                            sx={{
                              backgroundColor: WORKOUT_TYPES[run.type].color,
                              color: 'white',
                            }}
                          />
                        </TableCell>
                        <TableCell align="right">
                          {convertDistance(run.distance)} {getDistanceUnit()}
                        </TableCell>
                        <TableCell>
                          {run.targetPace} {getPaceUnit()}
                        </TableCell>
                        <TableCell>
                          <Typography variant="body2" style={{ whiteSpace: 'pre-line' }}>
                            {run.details}
                          </Typography>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </TableContainer>
            </DialogContent>
            <DialogActions>
              <Button onClick={handleCloseWeeklyDetails}>Close</Button>
            </DialogActions>
          </>
        )}
      </Dialog>
    </Box>
  );
};

export default TrainingPlan; 