import React from 'react';
import { Routes, Route } from 'react-router-dom';
import { Box } from '@mui/material';
import Navbar from './components/Navbar';
import Dashboard from './pages/Dashboard';
import Activities from './pages/Activities';
import Analytics from './pages/Analytics';
import Records from './pages/Records';
import TrainingPlan from './pages/TrainingPlan';
import Profile from './pages/Profile';
import Login from './pages/Login';
import { SettingsProvider } from './context/SettingsContext';

const App = () => {
  return (
    <SettingsProvider>
      <Box sx={{ display: 'flex', flexDirection: 'column', minHeight: '100vh' }}>
        <Navbar />
        <Box component="main" sx={{ flexGrow: 1, p: 3 }}>
          <Routes>
            <Route path="/" element={<Dashboard />} />
            <Route path="/activities" element={<Activities />} />
            <Route path="/analytics" element={<Analytics />} />
            <Route path="/records" element={<Records />} />
            <Route path="/training" element={<TrainingPlan />} />
            <Route path="/profile" element={<Profile />} />
            <Route path="/login" element={<Login />} />
          </Routes>
        </Box>
      </Box>
    </SettingsProvider>
  );
};

export default App; 