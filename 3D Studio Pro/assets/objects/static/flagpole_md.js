// objects/static/flagpole_md.js — 3D модель ресурса
import { createMoldovaFlagTexture } from '../../textures/moldova_flag.js';

export function build(d = {}) {
    const g = new THREE.Group();
    const s = parseFloat(d.scale || 1.0);
    const col1 = new THREE.MeshStandardMaterial({ color: d.color || '#15803d', roughness: 0.7 });
    const col2 = new THREE.MeshStandardMaterial({ color: d.accent || '#78350f', roughness: 0.8 });
    const metalMat = new THREE.MeshStandardMaterial({ color: 0x1f2937, metalness: 0.7, roughness: 0.3 });

    const base = new THREE.Mesh(new THREE.CylinderGeometry(0.2, 0.28, 0.25, 14), new THREE.MeshStandardMaterial({ color: 0x64748b }));
                    base.position.y = 0.125;
                    const pole = new THREE.Mesh(new THREE.CylinderGeometry(0.035, 0.045, 4.4, 12), new THREE.MeshStandardMaterial({ color: 0xe2e8f0, metalness: 0.85, roughness: 0.2 }));
                    pole.position.y = 2.2;
                    const finial = new THREE.Mesh(new THREE.SphereGeometry(0.075, 12, 12), new THREE.MeshStandardMaterial({ color: 0xfacc15, metalness: 0.85 }));
                    finial.position.y = 4.42;
                    const flagTex = createMoldovaFlagTexture();
                    const flagMat = new THREE.MeshStandardMaterial({ map: flagTex, side: THREE.DoubleSide, roughness: 0.6 });
                    const flagMesh = new THREE.Mesh(new THREE.PlaneGeometry(1.5, 0.75, 12, 4), flagMat);
                    flagMesh.position.set(0.77, 3.95, 0);
                    flagMesh.name = 'moldovaFlagCloth';
                    g.add(base, pole, finial, flagMesh);

    g.scale.set(s, s, s);
    g.traverse(m => { if (m.isMesh) { m.castShadow = true; m.receiveShadow = true; } });
    return g;
}

export default {
    id: 'flagpole_md',
    category: 'static',
    staticType: 'flagpole_md',
    radius: 0.45,
    build
};
