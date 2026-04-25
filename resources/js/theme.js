import { createTheme } from '@mui/material/styles';

const palettes = {
  "default": {
    "bgMain": "#1a2035",
    "bgSurface": "#151c2c",
    "primary": "#36d399"
  },
  "ace": {
    "bgMain": "#000000",
    "bgSurface": "rgba(18, 26, 48, 0.9)",
    "primary": "#36d399"
  },
  "azure": {
    "bgMain": "#051a2d",
    "bgSurface": "rgba(13, 35, 54, 0.84)",
    "primary": "#36d399"
  },
  "dino-cartoon-fun": {
    "bgMain": "#0e2134",
    "bgSurface": "rgba(23, 45, 62, 0.82)",
    "primary": "#36d399"
  },
  "discord-l": {
    "bgMain": "#121725",
    "bgSurface": "rgba(34, 39, 62, 0.85)",
    "primary": "#36d399"
  },
  "easter": {
    "bgMain": "#fff4fa",
    "bgSurface": "rgba(61, 43, 82, 0.82)",
    "primary": "#36d399"
  },
  "forest-night": {
    "bgMain": "#000000",
    "bgSurface": "rgba(16, 30, 21, 0.86)",
    "primary": "#36d399"
  },
  "gothic": {
    "bgMain": "#13070d",
    "bgSurface": "rgba(26, 14, 22, 0.86)",
    "primary": "#36d399"
  },
  "hacker": {
    "bgMain": "#030807",
    "bgSurface": "rgba(5, 14, 10, 0.92)",
    "primary": "#36d399"
  },
  "jurassic-summer": {
    "bgMain": "#122a12",
    "bgSurface": "rgba(20, 46, 20, 0.82)",
    "primary": "#36d399"
  },
  "light": {
    "bgMain": "#ffffff",
    "bgSurface": "#ffffff",
    "primary": "#36d399"
  },
  "m-bunicii": {
    "bgMain": "#14271c",
    "bgSurface": "#1f3d2b",
    "primary": "#e74c3c"
  },
  "minecraft": {
    "bgMain": "#0a180a",
    "bgSurface": "rgba(21, 30, 17, 0.82)",
    "primary": "#36d399"
  },
  "minimal-summer-clean": {
    "bgMain": "#ffffff",
    "bgSurface": "rgba(255, 255, 255, 0.88)",
    "primary": "#36d399"
  },
  "neon-circuit": {
    "bgMain": "#000000",
    "bgSurface": "rgba(10, 12, 22, 0.86)",
    "primary": "#36d399"
  },
  "ocean-deep-sea": {
    "bgMain": "#000000",
    "bgSurface": "rgba(3, 18, 42, 0.86)",
    "primary": "#36d399"
  },
  "retro-synth": {
    "bgMain": "#000000",
    "bgSurface": "rgba(26, 15, 47, 0.86)",
    "primary": "#36d399"
  },
  "school-again": {
    "bgMain": "#000000",
    "bgSurface": "rgba(45, 29, 18, 0.9)",
    "primary": "#36d399"
  },
  "sky-islands-fantasy": {
    "bgMain": "#0e2a55",
    "bgSurface": "rgba(18, 38, 72, 0.55)",
    "primary": "#36d399"
  },
  "sunset-gamer": {
    "bgMain": "#000000",
    "bgSurface": "rgba(28, 14, 39, 0.78)",
    "primary": "#36d399"
  },
  "super-dark": {
    "bgMain": "#000000",
    "bgSurface": "rgba(10, 10, 11, 0.96)",
    "primary": "#36d399"
  },
  "tropical-island": {
    "bgMain": "#07242d",
    "bgSurface": "rgba(10, 34, 46, 0.84)",
    "primary": "#36d399"
  },
  "winter-time": {
    "bgMain": "#000000",
    "bgSurface": "rgba(18, 38, 64, 0.88)",
    "primary": "#36d399"
  },
  "zombie-apocalipse": {
    "bgMain": "#000000",
    "bgSurface": "rgba(20, 25, 18, 0.92)",
    "primary": "#36d399"
  }
};

export const getTheme = (themeName = 'default') => {
  const t = palettes[themeName] || palettes['default'];

  return createTheme({
    palette: {
      mode: themeName === 'light' || themeName === 'minimal-summer-clean' ? 'light' : 'dark',
      primary: {
        main: t.primary,
      },
      background: {
        default: t.bgMain,
        paper: t.bgSurface,
      },
      text: {
        primary: themeName === 'light' || themeName === 'minimal-summer-clean' ? '#1e293b' : '#e2e8f0',
        secondary: themeName === 'light' || themeName === 'minimal-summer-clean' ? '#64748b' : '#94a3b8',
      },
    },
    typography: {
      fontFamily: '"Inter", "Outfit", "Helvetica", "Arial", sans-serif',
    },
    shape: {
      borderRadius: 8,
    },
    components: {
      MuiButton: {
        styleOverrides: {
          root: {
            textTransform: 'none',
            fontWeight: 600,
            padding: '10px 24px',
            boxShadow: 'none',
          },
        },
      },
      MuiTextField: {
        styleOverrides: {
          root: {
            '& .MuiOutlinedInput-root': {
              backgroundColor: 'rgba(0,0,0,0.1)',
              '&:hover .MuiOutlinedInput-notchedOutline': {
                borderColor: 'rgba(255, 255, 255, 0.15)',
              },
            },
          },
        },
      },
      MuiCard: {
        styleOverrides: {
          root: {
            backgroundImage: 'none',
            border: '1px solid rgba(255, 255, 255, 0.05)',
            boxShadow: 'none',
          },
        },
      },
      MuiDrawer: {
        styleOverrides: {
          paper: {
            backgroundColor: t.bgSurface,
          }
        }
      },
      MuiAppBar: {
        styleOverrides: {
          root: {
            backgroundColor: t.bgSurface,
            backgroundImage: 'none',
            boxShadow: 'none',
          }
        }
      }
    },
  });
};

export default getTheme('default');
