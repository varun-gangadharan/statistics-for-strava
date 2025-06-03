import React, { createContext, useContext, useState, useEffect } from 'react';

const SettingsContext = createContext();

export const useSettings = () => {
  const context = useContext(SettingsContext);
  if (!context) {
    throw new Error('useSettings must be used within a SettingsProvider');
  }
  return context;
};

export const SettingsProvider = ({ children }) => {
  const [useMetric, setUseMetric] = useState(() => {
    const saved = localStorage.getItem('useMetric');
    return saved !== null ? JSON.parse(saved) : true;
  });

  useEffect(() => {
    localStorage.setItem('useMetric', JSON.stringify(useMetric));
  }, [useMetric]);

  const toggleUnit = () => {
    setUseMetric(prev => !prev);
  };

  // Conversion helpers
  const convertDistance = (km) => {
    if (useMetric) return Number(km.toFixed(1));
    return Number((km * 0.621371).toFixed(1));
  };

  const getDistanceUnit = () => useMetric ? 'km' : 'mi';

  const convertElevation = (meters) => {
    if (useMetric) return Math.round(meters);
    return Math.round(meters * 3.28084);
  };

  const getElevationUnit = () => useMetric ? 'm' : 'ft';

  const convertPace = (metersPerSecond) => {
    const secondsPerUnit = useMetric 
      ? (1000 / metersPerSecond)  // seconds per km
      : (1609.34 / metersPerSecond);  // seconds per mile
    
    const minutes = Math.floor(secondsPerUnit / 60);
    const seconds = Math.floor(secondsPerUnit % 60);
    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
  };

  const getPaceUnit = () => useMetric ? '/km' : '/mi';

  const value = {
    useMetric,
    toggleUnit,
    convertDistance,
    getDistanceUnit,
    convertElevation,
    getElevationUnit,
    convertPace,
    getPaceUnit,
  };

  return (
    <SettingsContext.Provider value={value}>
      {children}
    </SettingsContext.Provider>
  );
}; 